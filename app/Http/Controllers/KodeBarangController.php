<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use App\Http\Requests\StoreKodeBarangRequest;
use App\Http\Requests\UpdateKodeBarangRequest;
use Illuminate\Http\Request;

class KodeBarangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function createCode()
    {
        //
        $group_names = KodeBarang::distinct()->pluck('attribute');
        return view('panels.add-code', compact('group_names'));
    }

    public function viewCode()
    {
        //
        $codes = KodeBarang::paginate(10);

        return view('panels.view-code', compact('codes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function storeCode(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'attribute' => 'required|string|max:255',
            'kode_barang' => 'required|string|max:255',
            'length' => 'required|numeric|min:0.1',
        ], [
            'kode_barang.required' => 'Item code is required',
            'kode_barang.string' => 'Item code must be a valid string',
            'kode_barang.max' => 'Item code may not be greater than 255 characters',

            'attribute.required' => 'Panel name is required',
            'attribute.string' => 'Panel name must be a valid string',
            'attribute.max' => 'Panel name may not be greater than 255 characters',

            'length.required' => 'Panel length is required',
            'length.numeric' => 'Panel length must be a number',
            'length.min' => 'Panel length must be at least 0.1 meters',

            'name.required' => 'Panel name is required',
            'name.string' => 'Panel name must be a valid string',
            'name.max' => 'Panel name may not be greater than 255 characters',

            'cost.required' => 'Cost is required',
            'cost.numeric' => 'Cost must be a valid number',
            'cost.min' => 'Cost must be at least 0',

            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price must be at least 0',
        ]);

        // $attribute = $validated['attribute'];
        // $length = $validated['length'];
        // $kode_barang = $validated['kode_barang'];
        $validated['status'] = 'Active';

        KodeBarang::create($validated);

        return redirect()->route('code.view-code')
            ->with('success', "Successfully add group code!");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKodeBarangRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(KodeBarang $kodeBarang)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
   /**
 * Show the form for editing the specified resource.
 */
    public function edit($id)
    {
        $viewPath = resource_path('views/panels/edit-code.blade.php');
        if (file_exists($viewPath)) {
            $code = KodeBarang::findOrFail($id);
            return view('panels.edit-code', compact('code'));
        } else {
            return "View file does not exist at: " . $viewPath;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validate the request
        $validated = $request->validate([
            'attribute' => 'required|string|max:255',
            'kode_barang' => 'required|string|max:255',
            'length' => 'required|numeric|min:0.1',
        ], [
            'kode_barang.required' => 'Item code is required',
            'kode_barang.string' => 'Item code must be a valid string',
            'kode_barang.max' => 'Item code may not be greater than 255 characters',

            'attribute.required' => 'Panel name is required',
            'attribute.string' => 'Panel name must be a valid string',
            'attribute.max' => 'Panel name may not be greater than 255 characters',

            'length.required' => 'Panel length is required',
            'length.numeric' => 'Panel length must be a number',
            'length.min' => 'Panel length must be at least 0.1 meters',
        ]);

        $code = KodeBarang::findOrFail($id);
        $code->update($validated);

        return redirect()->route('code.view-code')
            ->with('success', "Successfully updated code!");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $code = KodeBarang::findOrFail($id);
        $code->delete();

        return redirect()->route('code.view-code')
            ->with('success', "Successfully deleted code!");
    }

    public function searchKodeBarang(Request $request)
    {
        $keyword = $request->input('keyword');

        $kodeBarang = KodeBarang::where('kode_barang', 'like', "%{$keyword}%")
            ->orWhere('attribute', 'like', "%{$keyword}%")
            ->limit(10)
            ->get();

        return response()->json($kodeBarang);
    }
}
