<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDeliveryPhoto;
use App\Models\OrderItem;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query()->with(['representative', 'items', 'customer']);
        $this->scopeRepresentative($query, $request->user());

        if ($request->filled('status') && in_array($request->string('status')->toString(), [
            Order::STATUS_PURCHASING,
            Order::STATUS_AWAITING_DELIVERY,
            Order::STATUS_SHORTAGE,
            Order::STATUS_CLOSED,
        ], true)) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('orders.index', [
            'orders' => $query->latest()->paginate(15)->withQueryString(),
            'selectedStatus' => $request->string('status')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('orders.create', [
            'units' => ProductUnit::query()->where('active', true)->orderBy('name')->pluck('name'),
            'selectedCustomer' => old('customer_id')
                ? Customer::query()->where('active', true)->find(old('customer_id'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('active', true)],
            'delivery_location' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', Rule::exists('product_units', 'name')->where('active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        abort_unless($request->user()->hasPermission('customers.view'), 403);
        $customer = Customer::query()->whereKey($data['customer_id'])->where('active', true)->firstOrFail();

        $order = DB::transaction(function () use ($data, $request, $customer): Order {
            $order = Order::create([
                'order_number' => 'SUP-TEMP-'.strtoupper((string) Str::uuid()),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'delivery_location' => $data['delivery_location'],
                'notes' => $data['notes'] ?? null,
                'status' => Order::STATUS_PURCHASING,
                'representative_id' => $request->user()->id,
            ]);
            $order->update(['order_number' => Order::numberForId((int) $order->id)]);

            $auditItems = [];
            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_name' => $item['product_name'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
                $auditItems[] = [
                    'product_name' => $item['product_name'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_revenue' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
                ];
            }

            $this->audit($order, $request->user(), 'order_created', [
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'delivery_location' => $order->delivery_location,
                'notes' => $order->notes,
                'items' => $auditItems,
                'estimated_revenue' => round(array_sum(array_column($auditItems, 'line_revenue')), 2),
            ]);

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('success', 'تم إنشاء الطلب وتحويله إلى قسم المشتريات.');
    }

    public function show(Request $request, Order $order): View
    {
        $this->ensureVisible($order, $request->user());
        $order->load(['representative', 'items', 'customer', 'auditLogs.user', 'deliveryPhotos']);
        $suppliers = $request->user()->hasAnyPermission(['orders.purchase', 'orders.resolve_shortage'])
            ? Supplier::query()
                ->where(function (Builder $query) use ($order): void {
                    $query->where('active', true);
                    if ($order->supplier_name) {
                        $query->orWhere('name', $order->supplier_name);
                    }
                })
                ->orderBy('name')
                ->get()
            : collect();

        return view('orders.show', compact('order', 'suppliers'));
    }

    public function deliveryPhoto(Request $request, Order $order, OrderDeliveryPhoto $photo): BinaryFileResponse
    {
        $this->ensureVisible($order, $request->user());
        abort_unless((int) $photo->order_id === (int) $order->id, 404);
        abort_unless(in_array($photo->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($photo->path), 404);

        return response()->file($disk->path($photo->path), [
            'Content-Type' => $photo->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function edit(Order $order): View
    {
        $order->load('items');

        return view('orders.edit', [
            'order' => $order,
            'units' => ProductUnit::query()->orderBy('name')->pluck('name'),
            'representatives' => User::query()->where('role', User::ROLE_REPRESENTATIVE)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'delivery_location' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'representative_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_REPRESENTATIVE)],
            'status' => ['required', Rule::in([
                Order::STATUS_PURCHASING,
                Order::STATUS_AWAITING_DELIVERY,
                Order::STATUS_SHORTAGE,
                Order::STATUS_CLOSED,
            ])],
            'supplier_name' => ['nullable', 'string', 'max:255', Rule::exists('suppliers', 'name')],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'purchasing_confirmed_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('order_items', 'id')],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', Rule::exists('product_units', 'name')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.supplier_unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.missing_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'items.*.shortage_resolution' => ['nullable', Rule::in(['customer', 'same_supplier', 'alternate_supplier'])],
            'items.*.replacement_supplier' => ['nullable', 'string', 'max:255', Rule::exists('suppliers', 'name')],
            'items.*.replacement_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.replacement_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        $hasShortage = false;
        foreach ($data['items'] as $index => $item) {
            $missingQuantity = (float) $item['missing_quantity'];
            if ($missingQuantity > (float) $item['quantity']) {
                throw ValidationException::withMessages([
                    "items.{$index}.missing_quantity" => 'الكمية الناقصة لا يمكن أن تتجاوز كمية الطلب.',
                ]);
            }
            $hasShortage = $hasShortage || $missingQuantity > 0;
            if (in_array($item['shortage_resolution'], ['same_supplier', 'alternate_supplier'], true) && $missingQuantity > 0
                && (empty($item['replacement_supplier']) || ! isset($item['replacement_unit_cost'], $item['replacement_unit_price']))) {
                throw ValidationException::withMessages([
                    "items.{$index}.replacement_supplier" => 'أدخل مورد النقص وتكلفته وسعره لمعالجة النقص.',
                ]);
            }
            if ($item['shortage_resolution'] === 'same_supplier'
                && $missingQuantity > 0
                && $item['replacement_supplier'] !== $order->supplier_name) {
                throw ValidationException::withMessages([
                    "items.{$index}.replacement_supplier" => 'يجب أن يكون مورد النقص هو المورد الأساسي للطلب.',
                ]);
            }
        }

        if ($data['status'] === Order::STATUS_SHORTAGE && ! $hasShortage) {
            throw ValidationException::withMessages([
                'status' => 'حالة انتظار معالجة النقص تتطلب وجود كمية ناقصة.',
            ]);
        }
        if ($data['status'] === Order::STATUS_CLOSED
            && collect($data['items'])->contains(fn (array $item): bool => (float) $item['missing_quantity'] > 0 && empty($item['shortage_resolution']))) {
            throw ValidationException::withMessages([
                'status' => 'عالج جميع النواقص قبل إغلاق الطلب.',
            ]);
        }

        DB::transaction(function () use ($request, $order, $data): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existingItems = $lockedOrder->items()->orderBy('id')->lockForUpdate()->get();
            $lockedOrder->load('representative');
            $before = $this->orderSnapshot($lockedOrder, $existingItems);
            $existingIds = $existingItems->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $submittedIds = collect($data['items'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->all();

            abort_unless(
                count($submittedIds) === count(array_unique($submittedIds))
                    && array_diff($submittedIds, $existingIds) === [],
                422,
                'تحتوي البنود على معرّف غير تابع لهذا الطلب.',
            );

            $lockedOrder->update([
                'customer_id' => $data['customer_name'] === $lockedOrder->customer_name
                    && ($data['customer_phone'] ?? null) === $lockedOrder->customer_phone
                        ? $lockedOrder->customer_id
                        : null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'delivery_location' => $data['delivery_location'],
                'notes' => $data['notes'] ?? null,
                'representative_id' => $data['representative_id'],
                'status' => $data['status'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'purchasing_confirmed_at' => $data['purchasing_confirmed_at'] ?? null,
                'closed_at' => $data['status'] === Order::STATUS_CLOSED
                    ? ($data['closed_at'] ?? now())
                    : null,
            ]);

            foreach ($data['items'] as $itemData) {
                $itemValues = [
                    'product_name' => $itemData['product_name'],
                    'unit' => $itemData['unit'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'supplier_unit_cost' => $itemData['supplier_unit_cost'],
                    'missing_quantity' => $itemData['missing_quantity'],
                    'shortage_resolution' => $itemData['shortage_resolution'] ?? null,
                    'replacement_supplier' => $itemData['replacement_supplier'] ?? null,
                    'replacement_unit_cost' => $itemData['replacement_unit_cost'] ?? null,
                    'replacement_unit_price' => $itemData['replacement_unit_price'] ?? null,
                ];

                if (! empty($itemData['id'])) {
                    $existingItems->firstWhere('id', (int) $itemData['id'])->update($itemValues);
                } else {
                    $lockedOrder->items()->create($itemValues);
                }
            }

            $lockedOrder->items()->whereIn('id', array_diff($existingIds, $submittedIds))->delete();
            $lockedOrder->unsetRelation('representative')->load(['items', 'representative']);
            $this->audit($lockedOrder, $request->user(), 'order_updated', [
                'before' => $before,
                'after' => $this->orderSnapshot($lockedOrder, $lockedOrder->items),
            ]);
        });

        return redirect()->route('orders.show', $order)->with('success', 'تم تحديث بيانات الطلب وتسجيل التغييرات.');
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $orderNumber = $order->order_number;
        $photoPaths = $order->deliveryPhotos()->pluck('path');
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->delete();
        });
        foreach ($photoPaths as $path) {
            if (! Storage::disk('local')->delete($path)) {
                report(new RuntimeException("Unable to delete delivery photo: {$path}"));
            }
        }

        return redirect()->route('orders.index')->with('success', "تم حذف الطلب {$orderNumber} وبنوده وسجل تغييراته.");
    }

    public function confirmPurchase(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('active', true)],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        DB::transaction(function () use ($request, $order, $data): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === Order::STATUS_PURCHASING, 409, 'حالة الطلب لا تسمح بتأكيد الشراء.');
            $supplier = Supplier::query()->whereKey($data['supplier_id'])->where('active', true)->lockForUpdate()->firstOrFail();
            $items = $lockedOrder->items()->orderBy('id')->get();
            $this->ensureExactItemSet($items, $data['items']);

            $auditItems = [];
            foreach ($items as $item) {
                $values = $data['items'][$item->id];
                $item->update([
                    'supplier_unit_cost' => $values['supplier_unit_cost'],
                    'unit_price' => $values['unit_price'],
                ]);
                $auditItems[] = [
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'supplier_unit_cost' => $values['supplier_unit_cost'],
                    'unit_price' => $values['unit_price'],
                    'line_cost' => round((float) $item->quantity * (float) $values['supplier_unit_cost'], 2),
                    'line_revenue' => round((float) $item->quantity * (float) $values['unit_price'], 2),
                ];
            }

            $lockedOrder->update([
                'supplier_name' => $supplier->name,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'status' => Order::STATUS_AWAITING_DELIVERY,
                'purchasing_confirmed_at' => now(),
            ]);

            $this->audit($lockedOrder, $request->user(), 'purchase_confirmed', [
                'supplier_name' => $supplier->name,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'items' => $auditItems,
                'estimated_cost' => round(array_sum(array_column($auditItems, 'line_cost')), 2),
                'estimated_revenue' => round(array_sum(array_column($auditItems, 'line_revenue')), 2),
            ]);
        });

        return back()->with('success', 'تم تأكيد المورد والأسعار، وأُعيد الطلب إلى المندوب.');
    }

    public function reportShortage(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'delivery_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.missing_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
        ]);

        $this->runDeliveryAction(
            $order,
            $request->user(),
            $data['delivery_photo'],
            'shortage_reported',
            function (Order $lockedOrder) use ($request, $data): void {
                abort_unless($lockedOrder->status === Order::STATUS_AWAITING_DELIVERY, 409, 'حالة الطلب لا تسمح بتسجيل نقص.');
                $items = $lockedOrder->items()->orderBy('id')->get();
                $this->ensureExactItemSet($items, $data['items']);

                $hasShortage = false;
                $auditItems = [];
                foreach ($items as $item) {
                    $missing = (float) $data['items'][$item->id]['missing_quantity'];
                    if ($missing > (float) $item->quantity) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}.missing_quantity" => 'الكمية الناقصة لا يمكن أن تتجاوز كمية الطلب.',
                        ]);
                    }
                    $hasShortage = $hasShortage || $missing > 0;
                    $item->update([
                        'missing_quantity' => $missing,
                        'shortage_resolution' => null,
                        'replacement_supplier' => null,
                        'replacement_unit_cost' => null,
                        'replacement_unit_price' => null,
                    ]);
                    if ($missing > 0) {
                        $auditItems[] = [
                            'product_name' => $item->product_name,
                            'unit' => $item->unit,
                            'ordered_quantity' => (float) $item->quantity,
                            'missing_quantity' => $missing,
                            'received_quantity' => round((float) $item->quantity - $missing, 3),
                        ];
                    }
                }

                if (! $hasShortage) {
                    throw ValidationException::withMessages([
                        'items' => 'أدخل كمية ناقصة واحدة على الأقل، أو أكد التسليم الكامل.',
                    ]);
                }

                $lockedOrder->update(['status' => Order::STATUS_SHORTAGE]);
                $this->audit($lockedOrder, $request->user(), 'shortage_reported', [
                    'items' => $auditItems,
                    'next_step' => 'إعادة الطلب إلى المشتريات للتواصل مع المورد وتحديد موعد استكمال التوريد.',
                ]);
            },
        );

        return back()->with('success', 'تم تسجيل النقص وتحويل الطلب إلى قسم المشتريات.');
    }

    public function resolveShortage(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.resolution' => ['required', 'in:customer,same_supplier,alternate_supplier'],
            'items.*.replacement_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'items.*.replacement_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.replacement_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        DB::transaction(function () use ($request, $order, $data): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === Order::STATUS_SHORTAGE, 409, 'حالة الطلب لا تسمح بمعالجة النقص.');
            $missingItems = $lockedOrder->items()->where('missing_quantity', '>', 0)->orderBy('id')->get();
            $this->ensureExactItemSet($missingItems, $data['items']);

            $auditItems = [];
            foreach ($missingItems as $item) {
                $values = $data['items'][$item->id];
                if (in_array($values['resolution'], ['same_supplier', 'alternate_supplier'], true)) {
                    $validator = Validator::make($values, [
                        'replacement_supplier_id' => [
                            'required',
                            'integer',
                            $values['resolution'] === 'alternate_supplier'
                                ? Rule::exists('suppliers', 'id')->where('active', true)
                                : Rule::exists('suppliers', 'id'),
                        ],
                        'replacement_unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
                        'replacement_unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
                    ]);
                    if ($validator->fails()) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}.replacement_supplier_id" => 'اختر مورد النقص وأدخل تكلفته وسعره للعميل.',
                        ]);
                    }
                }

                $replacementSupplier = null;
                if ($values['resolution'] === 'same_supplier') {
                    $replacementSupplier = Supplier::query()
                        ->whereKey($values['replacement_supplier_id'])
                        ->where('name', $lockedOrder->supplier_name)
                        ->first();
                    if (! $replacementSupplier) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}.replacement_supplier_id" => 'اختر المورد الأساسي للطلب عند اختيار التوريد من نفس المورد.',
                        ]);
                    }
                } elseif ($values['resolution'] === 'alternate_supplier') {
                    $replacementSupplier = Supplier::query()
                        ->whereKey($values['replacement_supplier_id'])
                        ->where('active', true)
                        ->firstOrFail();
                }

                $item->update([
                    'shortage_resolution' => $values['resolution'],
                    'replacement_supplier' => $replacementSupplier?->name,
                    'replacement_unit_cost' => in_array($values['resolution'], ['same_supplier', 'alternate_supplier'], true) ? $values['replacement_unit_cost'] : null,
                    'replacement_unit_price' => in_array($values['resolution'], ['same_supplier', 'alternate_supplier'], true) ? $values['replacement_unit_price'] : null,
                ]);
                $auditItems[] = [
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'ordered_quantity' => (float) $item->quantity,
                    'missing_quantity' => (float) $item->missing_quantity,
                    'resolution' => $values['resolution'],
                    'replacement_supplier' => $replacementSupplier?->name,
                    'replacement_unit_cost' => in_array($values['resolution'], ['same_supplier', 'alternate_supplier'], true) ? $values['replacement_unit_cost'] : null,
                    'replacement_unit_price' => in_array($values['resolution'], ['same_supplier', 'alternate_supplier'], true) ? $values['replacement_unit_price'] : null,
                    'next_step' => $values['resolution'] === 'customer'
                        ? 'العميل يتولى تدبير الكمية الناقصة.'
                        : 'إعادة الطلب للمندوب للتأكد من استلام الكمية الناقصة.',
                ];
            }

            $lockedOrder->update(['status' => Order::STATUS_AWAITING_DELIVERY]);
            $this->audit($lockedOrder, $request->user(), 'shortage_resolved', [
                'items' => $auditItems,
                'next_step' => 'إعادة الطلب إلى المندوب للتأكد من استلام النواقص أو إغلاقها حسب المعالجة.',
            ]);
        });

        return back()->with('success', 'تم تحديث معالجة النواقص والأسعار وإعادة الطلب إلى المندوب لإغلاقه.');
    }

    public function close(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'delivery_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'delivery_outcome' => ['sometimes', 'in:complete,customer_shortage'],
            'items' => ['required_if:delivery_outcome,customer_shortage', 'array', 'min:1'],
            'items.*.missing_quantity' => ['required_if:delivery_outcome,customer_shortage', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
        ]);
        $deliveryOutcome = $data['delivery_outcome'] ?? 'complete';
        $event = $deliveryOutcome === 'customer_shortage' ? 'customer_shortage_closed' : 'order_closed';

        $this->runDeliveryAction(
            $order,
            $request->user(),
            $data['delivery_photo'],
            $event,
            function (Order $lockedOrder) use ($request, $data, $deliveryOutcome, $event): void {
                abort_unless($lockedOrder->status === Order::STATUS_AWAITING_DELIVERY, 409, 'لا يمكن إغلاق الطلب في حالته الحالية.');

                $items = $lockedOrder->items()->orderBy('id')->get();
                if ($deliveryOutcome === 'customer_shortage') {
                    $this->ensureExactItemSet($items, $data['items']);

                    $hasShortage = false;
                    foreach ($items as $item) {
                        $missing = (float) $data['items'][$item->id]['missing_quantity'];
                        if ($missing > (float) $item->quantity) {
                            throw ValidationException::withMessages([
                                "items.{$item->id}.missing_quantity" => 'الكمية الناقصة لا يمكن أن تتجاوز كمية الطلب.',
                            ]);
                        }

                        $hasShortage = $hasShortage || $missing > 0;
                        $item->update([
                            'missing_quantity' => $missing,
                            'shortage_resolution' => $missing > 0 ? 'customer' : null,
                            'replacement_supplier' => null,
                            'replacement_unit_cost' => null,
                            'replacement_unit_price' => null,
                        ]);
                    }

                    if (! $hasShortage) {
                        throw ValidationException::withMessages([
                            'items' => 'أدخل كمية ناقصة واحدة على الأقل، أو اختر تأكيد الاستلام الكامل.',
                        ]);
                    }
                } else {
                    $unresolved = $items->contains(
                        fn (OrderItem $item): bool => (float) $item->missing_quantity > 0
                            && $item->shortage_resolution === null,
                    );
                    abort_if($unresolved, 409, 'يجب على المشتريات معالجة النواقص قبل إغلاق الطلب.');
                }

                $lockedOrder->update([
                    'status' => Order::STATUS_CLOSED,
                    'closed_at' => now(),
                ]);
                $lockedOrder->load('items');
                $auditItems = $lockedOrder->items->map(function (OrderItem $item): array {
                    $missing = (float) $item->missing_quantity;
                    $deduction = $item->shortage_resolution === 'customer'
                        ? round($missing * (float) $item->unit_price, 2)
                        : 0.0;

                    return [
                        'product_name' => $item->product_name,
                        'unit' => $item->unit,
                        'ordered_quantity' => (float) $item->quantity,
                        'missing_quantity' => $missing,
                        'received_quantity' => round((float) $item->quantity - $missing, 3),
                        'shortage_resolution' => $item->shortage_resolution,
                        'deduction' => $deduction,
                    ];
                })->all();
                $this->audit($lockedOrder, $request->user(), $event, [
                    'outcome' => $deliveryOutcome,
                    'items' => $auditItems,
                    'deduction' => round(array_sum(array_column($auditItems, 'deduction')), 2),
                    'revenue' => $lockedOrder->revenue,
                    'cost' => $lockedOrder->cost,
                    'profit' => $lockedOrder->profit,
                ]);
            },
        );

        return back()->with(
            'success',
            $deliveryOutcome === 'customer_shortage'
                ? 'تم إغلاق الطلب بالنواقص بعد خصم قيمتها من حساب العميل.'
                : 'تم تأكيد التسليم وإغلاق الطلب.',
        );
    }

    private function runDeliveryAction(
        Order $order,
        User $user,
        UploadedFile $photo,
        string $event,
        Closure $transition,
    ): void {
        $storedPath = null;

        try {
            DB::transaction(function () use ($order, $user, $photo, $event, $transition, &$storedPath): void {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->ensureVisible($lockedOrder, $user);
                $transition($lockedOrder);

                $storedPath = $photo->store('order-delivery', 'local');
                if (! is_string($storedPath)) {
                    throw new RuntimeException('Unable to store delivery photo.');
                }

                $lockedOrder->deliveryPhotos()->create([
                    'user_id' => $user->id,
                    'event' => $event,
                    'path' => $storedPath,
                    'mime_type' => $photo->getMimeType(),
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath) && ! Storage::disk('local')->delete($storedPath)) {
                report(new RuntimeException("Unable to clean up delivery photo after a failed transition: {$storedPath}"));
            }

            throw $exception;
        }
    }

    private function scopeRepresentative(Builder $query, User $user): void
    {
        if ($user->role === User::ROLE_REPRESENTATIVE) {
            $query->where('representative_id', $user->id);
        }
    }

    private function ensureVisible(Order $order, User $user): void
    {
        abort_if(
            $user->role === User::ROLE_REPRESENTATIVE && $order->representative_id !== $user->id,
            404,
        );
    }

    private function ensureExactItemSet(EloquentCollection $items, array $postedItems): void
    {
        $expected = $items->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $received = collect(array_keys($postedItems))->map(fn ($id): int => (int) $id)->sort()->values()->all();

        abort_unless($expected === $received, 422, 'يجب إدخال بيانات جميع البنود المطلوبة دون إضافة بنود أخرى.');
    }

    private function audit(Order $order, User $user, string $event, array $details): void
    {
        AuditLog::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'event' => $event,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    private function orderSnapshot(Order $order, EloquentCollection $items): array
    {
        return [
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_location' => $order->delivery_location,
            'notes' => $order->notes,
            'representative_id' => $order->representative_id,
            'representative_name' => $order->representative?->name,
            'status' => $order->status,
            'supplier_name' => $order->supplier_name,
            'supplier_reference' => $order->supplier_reference,
            'purchasing_confirmed_at' => $order->purchasing_confirmed_at?->toISOString(),
            'closed_at' => $order->closed_at?->toISOString(),
            'items' => $items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'supplier_unit_cost' => $item->supplier_unit_cost,
                'missing_quantity' => $item->missing_quantity,
                'shortage_resolution' => $item->shortage_resolution,
                'replacement_supplier' => $item->replacement_supplier,
                'replacement_unit_cost' => $item->replacement_unit_cost,
                'replacement_unit_price' => $item->replacement_unit_price,
            ])->all(),
        ];
    }
}
