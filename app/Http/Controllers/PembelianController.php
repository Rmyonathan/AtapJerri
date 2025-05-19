<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Models\Panel;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class PembelianController extends Controller
{
    protected $stockController;
    protected $panelController;

    public function __construct(StockController $stockController, PanelController $panelController)
    {
        $this->stockController = $stockController;
        $this->panelController = $panelController;
    }

    /**
     * Display the purchase transaction form.
     */
    public function index()
    {
        // Ambil nomor nota terakhir
        $lastPurchase = Pembelian::orderBy('created_at', 'desc')->first();

        // Generate nomor nota baru
        if ($lastPurchase) {
            // Ambil angka terakhir dari nota
            $lastNumber = (int) substr($lastPurchase->nota, strrpos($lastPurchase->nota, '-') + 1);
            $newNumber = $lastNumber + 1;
        } else {
            // Jika belum ada pembelian, mulai dari 1
            $newNumber = 1;
        }

        // Format nomor nota baru
        $currentMonth = date('m');
        $currentYear = date('y');
        $nota = "BL/{$currentMonth}/{$currentYear}-" . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

        return view('pembelian.addpembelian', compact('nota'));
    }

    /**
     * Store a purchase transaction.
     */
    public function store(Request $request)
    {
        // In the store method, update the validation
        $request->validate([
            'nota' => 'required|string|unique:pembelian,nota',
            'tanggal' => 'required|date',
            'kode_supplier' => 'required|exists:suppliers,kode_supplier',
            'subtotal' => 'required|numeric',
            'grand_total' => 'required|numeric',
            'items' => 'required|array',
            'items.*.kodeBarang' => 'required|string',
            'items.*.harga' => 'required|numeric',
            'items.*.qty' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // Get supplier name for stock mutation record
            $supplier = Supplier::where('kode_supplier', $request->kode_supplier)->first();
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';

            // Create purchase
            $pembelian = Pembelian::create([
                'nota' => $request->nota,
                'tanggal' => $request->tanggal,
                'kode_supplier' => $request->kode_supplier,
                'pembayaran' => $request->pembayaran ?? 'Tunai',
                'cara_bayar' => $request->cara_bayar,
                'subtotal' => $request->subtotal,
                'diskon' => $request->diskon ?? 0,
                'ppn' => $request->ppn ?? 0,
                'grand_total' => $request->grand_total,
            ]);

            // Get creator name from request or default to 'ADMIN'
            $creator = Auth::check() ? Auth::user()->name : 'ADMIN';
            // Format transaction number for mutation record
            $noTransaksi = "BL-" . date('m/y', strtotime($request->tanggal)) . "-" .
                            substr($request->nota, strrpos($request->nota, '-') + 1) .
                            " ({$creator})";

            // Create purchase items, update stock mutation, and add inventory
            foreach ($request->items as $item) {
                // Create purchase item
                PembelianItem::create([
                    'nota' => $request->nota,
                    'kode_barang' => $item['kodeBarang'],
                    'nama_barang' => $item['namaBarang'],
                    'keterangan' => $item['keterangan'] ?? null,
                    'harga' => $item['harga'],
                    'qty' => $item['qty'],
                    'diskon' => $item['diskon'] ?? 0,
                    'total' => $item['total'],
                ]);

                // Record purchase in stock mutation (just for reporting)
                $this->stockController->recordPurchase(
                    $item['kodeBarang'],
                    $item['namaBarang'],
                    $noTransaksi,
                    $request->tanggal,
                    $request->nota,
                    $supplierName . ' (' . $request->kode_supplier . ')',
                    $item['qty'],
                    'default', // Use a default value or remove parameter if StockController is also updated
                    'LBR'
                );

                // Get the kode barang record
                $kodeBarang = KodeBarang::where('kode_barang', $item['kodeBarang'])->first();

                if ($kodeBarang) {
                    // No longer updating the master cost
                    // Just use the entered price for this specific purchase

                    // Get a panel instance with this kode_barang to use as a template
                    $templatePanel = Panel::where('group_id', $item['kodeBarang'])->first();

                    // Default values if no template exists
                    $panelName = $item['namaBarang'];
                    $length = $kodeBarang->length ?? 0;
                    $cost = $item['harga']; // Use purchase price as cost for this purchase only
                    $price = $templatePanel ? $templatePanel->price : ($item['harga'] * 1.2); // 20% markup if no template

                    // If template exists, use its values
                    if ($templatePanel) {
                        $panelName = $templatePanel->name;
                        $length = $templatePanel->length ?? $length;
                        $price = $templatePanel->price;
                    }

                    // Use the PanelController to add panels to inventory
                    $panelController = app()->make(PanelController::class);
                    $result = $panelController->addPanelsToInventory($panelName, $cost, $price, $length, $item['kodeBarang'], $item['qty']);

                    // Log the result
                    Log::info('Added panels to inventory:', ['result' => $result]);
                } else {
                    Log::warning('KodeBarang not found for purchase item:', ['kode_barang' => $item['kodeBarang']]);
                }
            }

            DB::commit();

            return response()->json([
                'id' => $pembelian->id,
                'nota' => $pembelian->nota,
                'tanggal' => $pembelian->tanggal,
                'supplier' => $pembelian->supplierRelation->nama ?? 'N/A',
                'grand_total' => $pembelian->grand_total,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian store:', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for suppliers
     */
    public function searchSuppliers(Request $request)
    {
        $keyword = $request->keyword;

        $suppliers = Supplier::where('kode_supplier', 'like', "%{$keyword}%")
            ->orWhere('nama', 'like', "%{$keyword}%")
            ->limit(10)
            ->get();

        return response()->json($suppliers);
    }

    /**
     * Get purchase data
     */
    public function getPurchase($id)
    {
        $purchase = Pembelian::with('items')->findOrFail($id);

        return response()->json($purchase);
    }

    /**
     * Show the invoice (nota) for a purchase
     */
    public function showNota($id)
    {
        $purchase = Pembelian::with('items', 'supplierRelation')->findOrFail($id);

        return view('pembelian.nota_pembelian', compact('purchase'));
    }

    public function nota($nota)
    {
        $purchase = Pembelian::with('items', 'supplierRelation')->where('nota', $nota)->firstOrFail();
        return view('pembelian.nota_pembelian', compact('purchase'));
    }

    public function listNota()
    {
        // Fetch all purchases
        $purchases = Pembelian::with('items')
            ->orderBy('created_at', 'desc')
            ->paginate(5);

        return view('pembelian.lihat_nota_pembelian', compact('purchases'));
    }

    /**
     * Show the form for editing the specified purchase.
     */
    public function edit($id)
    {
        $purchase = Pembelian::with(['items', 'supplierRelation'])->findOrFail($id);

        // Get the supplier info
        $supplier = null;
        if ($purchase->supplierRelation) {
            $supplier = $purchase->kode_supplier . ' - ' . $purchase->supplierRelation->nama;
        }

        return view('pembelian.editpembelian', compact('purchase', 'supplier'));
    }

    /**
     * Update the specified purchase in storage.
     */
    public function update(Request $request, $id)
    {
        // In the store method, update the validation
        $request->validate([
            'nota' => 'required|string|unique:pembelian,nota',
            'tanggal' => 'required|date',
            'kode_supplier' => 'required|exists:suppliers,kode_supplier',
            'subtotal' => 'required|numeric',
            'grand_total' => 'required|numeric',
            'items' => 'required|array',
            'items.*.kodeBarang' => 'required|string',
            'items.*.harga' => 'required|numeric',
            'items.*.qty' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // Find purchase
            $pembelian = Pembelian::findOrFail($id);
            $nota = $pembelian->nota; // Keep the original nota

            // Get supplier name for stock mutation record
            $supplier = Supplier::where('kode_supplier', $request->kode_supplier)->first();
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';

            // Get creator name from request or default to 'ADMIN'
            $creator = $request->updated_by ?? 'ADMIN';

            // Format transaction number for mutation record
            $noTransaksi = "BL-" . date('m/y', strtotime($request->tanggal)) . "-" .
                            substr($nota, strrpos($nota, '-') + 1) .
                            " ({$creator}) [UPDATED]";

            // Get the original items to remove from inventory
            $originalItems = PembelianItem::where('nota', $nota)->get();

            // Track panels to delete
            $panelsToDelete = [];

            // For each original item, find and mark panels for deletion
            foreach ($originalItems as $item) {
                // Find panels with this group_id that match the original purchase
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->orderBy('created_at', 'desc') // Get the most recently added first (likely from this purchase)
                    ->limit($item->qty)
                    ->get();

                foreach ($panels as $panel) {
                    $panelsToDelete[] = $panel->id;
                }

                // Record sale to reverse the original purchase in stock mutation
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksi,
                    now(), // Use current date/time for the reversal
                    $nota . ' (reversal)',
                    $supplierName . ' (' . $request->kode_supplier . ')',
                    $item->qty, // Same quantity as purchase, but as a "sale" to reduce stock
                    $pembelian->cabang,
                    'LBR'
                );
            }

            // Delete the marked panels
            if (!empty($panelsToDelete)) {
                Panel::whereIn('id', $panelsToDelete)->delete();
            }

            // Update purchase
            $pembelian->update([
                'tanggal' => $request->tanggal,
                'kode_supplier' => $request->kode_supplier,
                'pembayaran' => $request->pembayaran ?? 'Tunai',
                'cara_bayar' => $request->cara_bayar,
                'subtotal' => $request->subtotal,
                'diskon' => $request->diskon ?? 0,
                'ppn' => $request->ppn ?? 0,
                'grand_total' => $request->grand_total,
            ]);

            // Delete all existing items
            PembelianItem::where('nota', $nota)->delete();

            // Create new purchase items and add new inventory
            foreach ($request->items as $item) {
                PembelianItem::create([
                    'nota' => $nota,
                    'kode_barang' => $item['kodeBarang'],
                    'nama_barang' => $item['namaBarang'],
                    'keterangan' => $item['keterangan'] ?? null,
                    'harga' => $item['harga'],
                    'qty' => $item['qty'],
                    'diskon' => $item['diskon'] ?? 0,
                    'total' => $item['total'],
                ]);

                // Record new purchase in stock mutation
                $this->stockController->recordPurchase(
                    $item['kodeBarang'],
                    $item['namaBarang'],
                    $noTransaksi,
                    $request->tanggal,
                    $nota . ' (updated)',
                    $supplierName . ' (' . $request->kode_supplier . ')',
                    $item['qty'],
                    $request->cabang,
                    'LBR'
                );

                // Get the kode barang record
                $kodeBarang = KodeBarang::where('kode_barang', $item['kodeBarang'])->first();

                if ($kodeBarang) {
                    // Update the cost/harga beli in KodeBarang if it's different
                    if ($kodeBarang->cost != $item['harga']) {
                        $kodeBarang->cost = $item['harga'];
                        $kodeBarang->save();

                        // Log that the cost was updated
                        Log::info('Updated KodeBarang cost during update:', [
                            'kode_barang' => $item['kodeBarang'],
                            'old_cost' => $kodeBarang->getOriginal('cost'),
                            'new_cost' => $item['harga']
                        ]);
                    }

                    // Get a panel instance with this kode_barang to use as a template
                    $templatePanel = Panel::where('group_id', $item['kodeBarang'])->first();

                    // Default values if no template exists
                    $panelName = $item['namaBarang'];
                    $length = $kodeBarang->length ?? 0;
                    $cost = $item['harga']; // Use purchase price as cost
                    $price = $templatePanel ? $templatePanel->price : ($item['harga'] * 1.2); // 20% markup if no template

                    // If template exists, use its values
                    if ($templatePanel) {
                        $panelName = $templatePanel->name;
                        $length = $templatePanel->length ?? $length;
                        $price = $templatePanel->price;
                    }

                    // Use the PanelController to add panels to inventory
                    $panelController = app()->make(PanelController::class);
                    $result = $panelController->addPanelsToInventory($panelName, $cost, $price, $length, $item['kodeBarang'], $item['qty']);

                    // Log the result
                    Log::info('Added panels to inventory during update:', ['result' => $result]);
                } else {
                    Log::warning('KodeBarang not found for updated purchase item:', ['kode_barang' => $item['kodeBarang']]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'id' => $pembelian->id,
                'nota' => $pembelian->nota,
                'message' => 'Pembelian berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian update:', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified purchase from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // Find purchase
            $pembelian = Pembelian::findOrFail($id);
            $nota = $pembelian->nota;

            // Get supplier name for stock mutation record
            $supplier = $pembelian->supplierRelation;
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';

            // Get creator name or default to 'ADMIN'
            $creator = 'ADMIN';

            // Format transaction number for deletion record
            $noTransaksi = "BL-" . date('m/y', strtotime($pembelian->tanggal)) . "-" .
                           substr($nota, strrpos($nota, '-') + 1) .
                           " ({$creator}) [DELETED]";

            // Get the items to remove from inventory
            $items = PembelianItem::where('nota', $nota)->get();

            // Track panels to delete
            $panelsToDelete = [];

            foreach ($items as $item) {
                // Find panels with this group_id that match the purchase being deleted
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->orderBy('created_at', 'desc') // Get the most recently added first (likely from this purchase)
                    ->limit($item->qty)
                    ->get();

                foreach ($panels as $panel) {
                    $panelsToDelete[] = $panel->id;
                }

                // Record sale to reverse the purchase in stock mutation
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksi,
                    now(), // Use current date/time for the deletion
                    $nota . ' (deleted)',
                    $supplierName . ' (' . $pembelian->kode_supplier . ')',
                    $item->qty, // Same quantity as purchase, but as a "sale" to reduce stock
                    'default',
                    'LBR'
                );
            }

            // Delete the marked panels
            if (!empty($panelsToDelete)) {
                Log::info('Deleting panels:', ['panel_ids' => $panelsToDelete]);
                Panel::whereIn('id', $panelsToDelete)->delete();
            }

            // Delete all related items first
            PembelianItem::where('nota', $nota)->delete();

            // Delete the purchase
            $pembelian->delete();

            DB::commit();

            return redirect()->route('pembelian.nota.list')
                ->with('success', 'Nota pembelian berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian destroy:', ['exception' => $e->getMessage()]);

            return redirect()->route('pembelian.nota.list')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
 * Cancel a purchase transaction
 */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Find purchase
            $pembelian = Pembelian::findOrFail($id);

            // Check if already canceled
            if ($pembelian->status === 'canceled') {
                return redirect()->back()->with('error', 'Nota pembelian sudah dibatalkan sebelumnya.');
            }

            $nota = $pembelian->nota;

            // Get supplier name for stock mutation record
            $supplier = $pembelian->supplierRelation;
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';

            // Get current user or default to 'ADMIN'
            // Fix: Use Auth::user() instead of auth()->user()
            $canceledBy = Auth::check() ? Auth::user()->name : 'ADMIN';

            // Format transaction number for cancellation record
            $noTransaksi = "BL-" . date('m/y', strtotime($pembelian->tanggal)) . "-" .
                        substr($nota, strrpos($nota, '-') + 1) .
                        " ({$canceledBy}) [CANCELED]";

            // Get the items to replenish inventory
            $items = PembelianItem::where('nota', $nota)->get();

            // Track panels to mark as unavailable
            $panelsToCancel = [];

            foreach ($items as $item) {
                // Find panels with this group_id that match the purchase being canceled
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->orderBy('created_at', 'desc') // Get the most recently added first (likely from this purchase)
                    ->limit($item->qty)
                    ->get();

                foreach ($panels as $panel) {
                    $panelsToCancel[] = $panel->id;
                }

                // Record sale to reverse the purchase in stock mutation
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksi,
                    now(), // Use current date/time for the cancellation
                    $nota . ' (canceled)',
                    $supplierName . ' (' . $pembelian->kode_supplier . ')',
                    $item->qty, // Same quantity as purchase, but as a "sale" to reduce stock
                    $pembelian->cabang,
                    'LBR',
                    'Transaction canceled: ' . $request->cancel_reason
                );
            }

            // Mark the panels as unavailable but do not delete them
            if (!empty($panelsToCancel)) {
                Log::info('Marking panels as unavailable:', ['panel_ids' => $panelsToCancel]);
                Panel::whereIn('id', $panelsToCancel)->update(['available' => false]);
            }

            // Update the purchase as canceled
            $pembelian->update([
                'status' => 'canceled',
                'canceled_by' => $canceledBy,
                'canceled_at' => now(),
                'cancel_reason' => $request->cancel_reason
            ]);

            DB::commit();

            return redirect()->route('pembelian.nota.list')
                ->with('success', 'Nota pembelian berhasil dibatalkan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian cancel:', ['exception' => $e->getMessage()]);

            return redirect()->route('pembelian.nota.list')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
    }
