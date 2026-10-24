<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductUnitController extends Controller
{
    public function index(): View
    {
        return view('units.index', [
            'units' => ProductUnit::query()
                ->orderByDesc('active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('product_units', 'name')],
        ]);

        ProductUnit::create([
            'name' => trim($data['name']),
            'active' => true,
        ]);

        return back()->with('success', 'تمت إضافة الوحدة وأصبحت متاحة للاختيار في الطلبات الجديدة.');
    }

    public function update(Request $request, ProductUnit $unit): RedirectResponse
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        if (! $data['active']) {
            $lastActiveUnit = ProductUnit::query()
                ->where('active', true)
                ->where('id', '!=', $unit->id)
                ->doesntExist();

            if ($unit->active && $lastActiveUnit) {
                return back()->withErrors([
                    'unit' => 'يجب إبقاء وحدة واحدة على الأقل متاحة للطلبات الجديدة.',
                ]);
            }
        }

        $unit->update(['active' => $data['active']]);

        return back()->with('success', $data['active']
            ? 'تم تفعيل الوحدة للاستخدام في الطلبات الجديدة.'
            : 'تم إيقاف الوحدة عن الطلبات الجديدة؛ تبقى محفوظة في الطلبات السابقة.');
    }

    public function destroy(ProductUnit $unit): RedirectResponse
    {
        if (DB::table('order_items')->where('unit', $unit->name)->exists()) {
            return back()->withErrors([
                'unit' => 'لا يمكن حذف هذه الوحدة لأنها مستخدمة في طلب سابق. يمكنك إيقافها بدلًا من حذفها.',
            ]);
        }

        if ($unit->active && ProductUnit::query()->where('active', true)->count() === 1) {
            return back()->withErrors([
                'unit' => 'لا يمكن حذف آخر وحدة مفعّلة. أضف وحدة أخرى أو أوقف هذه الوحدة بعد تفعيل بديلة.',
            ]);
        }

        $unit->delete();

        return back()->with('success', 'تم حذف الوحدة بنجاح.');
    }
}
