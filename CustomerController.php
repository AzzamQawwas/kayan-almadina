<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        abort_unless(in_array($status, ['', 'active', 'inactive'], true), 404);

        $query = Customer::query()->withCount('orders')->orderBy('name');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        return view('customers.index', [
            'customers' => $query->paginate(20)->withQueryString(),
            'selectedStatus' => $status,
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()
                ->with(['representative', 'items'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);
        $term = trim($data['q']);
        $query = Customer::query()->where('active', true);
        if (preg_match('/^CUS-(\d+)$/i', $term, $matches) === 1) {
            $query->whereKey((int) $matches[1]);
        } else {
            $query->where(function ($customerQuery) use ($term): void {
                $customerQuery
                    ->where('name', 'like', $term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%');
            });
        }
        $customers = $query->orderBy('name')->limit(20)->get(['id', 'name', 'phone']);

        return response()->json([
            'customers' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'number' => $customer->customerNumber(),
                'name' => $customer->name,
                'phone' => $customer->phone,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedCustomer($request);
        Customer::create($data + ['active' => true]);

        return back()->with('success', 'تمت إضافة العميل بنجاح.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $data = $this->validatedCustomer($request);
        $customer = Customer::create($data + ['active' => true]);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'number' => $customer->customerNumber(),
                'name' => $customer->name,
                'phone' => $customer->phone,
            ],
        ], 201);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validatedCustomer($request, allowActiveOnly: true);
        $customer->update($data);

        return back()->with('success', array_key_exists('active', $data)
            ? ($data['active'] ? 'تم تفعيل العميل.' : 'تم إيقاف العميل عن الظهور في الطلبات الجديدة.')
            : 'تم تحديث بيانات العميل.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->orders()->exists()) {
            return back()->withErrors([
                'customer' => 'لا يمكن حذف هذا العميل لارتباطه بطلب سابق. يمكنك إيقافه بدلًا من حذفه.',
            ]);
        }

        $customer->delete();

        return back()->with('success', 'تم حذف العميل بنجاح.');
    }

    private function validatedCustomer(Request $request, bool $allowActiveOnly = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($allowActiveOnly) {
            $rules = [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'phone' => $request->has('name')
                    ? ['required', 'string', 'max:50']
                    : ['sometimes', 'nullable', 'string', 'max:50'],
                'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'active' => ['sometimes', 'required', 'boolean'],
            ];
        }

        $data = $request->validate($rules);

        if ($allowActiveOnly && $data === []) {
            throw ValidationException::withMessages([
                'customer' => 'لم يتم إرسال بيانات صالحة لتحديث العميل.',
            ]);
        }

        return $data;
    }
}
