<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Customer;
use App\Models\Panel;
use App\Models\Transaksi;
use App\Models\TransaksiItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class PurchaseOrderController extends Controller
{
    protected $stockController;

    public function __construct(StockController $stockController)
    {
        $this->stockController = $stockController;
    }

    private function generateNoPO()
    {
        $now = now();
        $prefix = 'PO-' . $now->format('my'); // ex: PO-0625

        $latestPO = PurchaseOrder::whereRaw("DATE_FORMAT(tanggal, '%m%y') = ?", [$now->format('my')])
            ->orderBy('no_po', 'desc')
            ->first();

        $lastNumber = 0;

        if ($latestPO) {
            // ambil 5 digit terakhir
            $lastNumber = (int) substr($latestPO->no_po, -5);
        }

        $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT); // padding nol depan
        return $prefix . '-' . $newNumber;
    }

    public function index(Request $request)
    {
        $searchBy = $request->input('search_by');
        $keyword = $request->input('search');
        $query = PurchaseOrder::with('items');

        if ($searchBy && $keyword) {
            if ($searchBy === 'no_po') {
                $query->where('no_po', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'kode_customer') {
                $query->where('kode_customer', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'sales') {
                $query->where('sales', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'status') {
                $query->where('status', 'like', "%{$keyword}%");
            }
        } elseif ($keyword) {
            // Default: cari di no_po dan kode_customer
            $query->where(function($q) use ($keyword) {
                $q->where('no_po', 'like', "%{$keyword}%")
                ->orWhere('kode_customer', 'like', "%{$keyword}%");
            });
        }

        $purchaseOrders = $query->orderBy('tanggal', 'desc')->paginate(10)->withQueryString();

        return view('transaksi.purchaseorder', compact('purchaseOrders', 'searchBy', 'keyword'));
    }

    public function show($id)
    {
        $po = PurchaseOrder::with(['items', 'customer'])->findOrFail($id);
        return view('transaksi.purchaseorder_detail', compact('po'));
    }

    public function store(Request $request)
    {
        Log::info('PO Store Request:', $request->all());

        try {
            // Parse items if they're sent as JSON string
            if ($request->has('items') && is_string($request->items)) {
                $parsedItems = json_decode($request->items, true);
                // Create a new request with the parsed items
                $request->merge(['items' => $parsedItems]);
            }

            // Validate the request
            $request->validate([
                'tanggal' => 'required|date',
                'kode_customer' => 'required|exists:customers,kode_customer',
                'sales' => 'required|exists:stok_owners,kode_stok_owner',
                'subtotal' => 'required|numeric',
                'grand_total' => 'required|numeric',
                'items' => 'required|array|min:1',
                'items.*.kodeBarang' => 'required|exists:panels,group_id',
                'items.*.harga' => 'required|numeric',
                'items.*.qty' => 'required|numeric|min:1',
            ]);

            DB::beginTransaction();

            Log::info('PO Validation passed');

            // Create purchase order
            $po = PurchaseOrder::create([
                'no_po' => $this->generateNoPO(),
                'tanggal' => $request->tanggal,
                'kode_customer' => $request->kode_customer,
                'sales' => $request->sales,
                'lokasi' => $request->lokasi,
                'pembayaran' => $request->pembayaran,
                'cara_bayar' => $request->cara_bayar,
                'tanggal_jadi' => null,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount ?? 0,
                'disc_rupiah' => $request->disc_rupiah ?? 0,
                'ppn' => $request->ppn,
                'dp' => $request->dp ?? 0,
                'grand_total' => $request->grand_total,
                'status' => 'pending',
            ]);

            Log::info('PO Created:', ['po_id' => $po->id, 'no_po' => $po->no_po]);

            // Create PO items
            foreach ($request->items as $item) {
                Log::info('Processing item:', $item);

                $po->items()->create([
                    'kode_barang' => $item['kodeBarang'],
                    'nama_barang' => $item['namaBarang'],
                    'keterangan' => $item['keterangan'] ?? '',
                    'harga' => $item['harga'],
                    'panjang' => $item['panjang'] ?? 0,
                    'qty' => $item['qty'],
                    'total' => $item['total'],
                    'diskon' => $item['diskon'] ?? 0,
                ]);
            }

            DB::commit();
            Log::info('PO creation completed successfully');

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order created successfully.',
                'po_id' => $po->id,
                'no_po' => $po->no_po
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PO Creation Error:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function cancel($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status === 'pending') {
            $userName = Auth::user() ? Auth::user()->name : 'USER DEFAULT';
            $po->update(['status' => 'cancelled by ' . $userName]);
        }

        return redirect()->route('transaksi.purchaseorder')->with('success', 'PO dibatalkan.');
    }


    public function completeTransaction($id)
    {
    Log::info('CompleteTransaction started for PO ID: ' . $id);

    $po = PurchaseOrder::with('items', 'customer')->findOrFail($id);

    DB::beginTransaction();

    try {
        Log::info('PO Data:', $po->toArray());

            // Generate a new transaction number
            $lastTransaction = Transaksi::orderBy('created_at', 'desc')->first();
            $lastNumber = $lastTransaction ? (int) substr($lastTransaction->no_transaksi, strrpos($lastTransaction->no_transaksi, '/') + 1) : 0;
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $noTransaksi = 'KP/WS/' . $newNumber;

            // Get customer for stock mutation
            $customer = Customer::where('kode_customer', $po->kode_customer)->first();
            $customerName = $customer ? $customer->nama : 'Unknown Customer';

            // Format transaction number for stock mutation
            $creator = 'ADMIN'; // You can replace this with the actual user name
            $noTransaksiMutasi = $noTransaksi . " ({$creator})";

            // Create a new transaction
            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'tanggal' => now(),
                'kode_customer' => $po->kode_customer,
                'sales' => $po->sales,
                'lokasi' => $po->lokasi,
                'pembayaran' => $po->pembayaran,
                'cara_bayar' => $po->cara_bayar,
                'tanggal_jadi' => now(),
                'subtotal' => $po->subtotal,
                'discount' => $po->discount,
                'disc_rupiah' => $po->disc_rupiah,
                'ppn' => $po->ppn,
                'dp' => $po->dp,
                'grand_total' => $po->grand_total,
                'status' => 'baru',
            ]);

            // Create transaction items and record stock mutations
            foreach ($po->items as $item) {
                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'no_transaksi' => $noTransaksi,
                    'kode_barang' => $item->kode_barang,
                    'nama_barang' => $item->nama_barang,
                    'keterangan' => $item->keterangan ?? null,
                    'harga' => $item->harga,
                    'panjang' => $item->panjang ?? 0,
                    'lebar' => $item->lebar ?? 0,
                    'qty' => $item->qty,
                    'diskon' => $item->diskon ?? 0,
                    'total' => $item->total,
                ]);

                // Record the sale in stock mutation
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksiMutasi,
                    now(),
                    $noTransaksi,
                    $customerName . ' (' . $po->kode_customer . ')',
                    $item->qty,
                    $po->lokasi ?? 'ALUMKA',
                    'LBR'
                );

                // Update panel availability
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->limit($item->qty)
                    ->get();

                foreach ($panels as $panel) {
                    $panel->available = false;
                    $panel->save();
                }
            }

            // Update the status of the Purchase Order
            $po->update([
                'status' => 'completed',
                'tanggal_jadi' => now(),
            ]);

            DB::commit();
            Log::info('Transaction completed successfully'); // Debug log

            return redirect()->route('transaksi.listnota')->with('success', 'Transaksi berhasil diselesaikan.');

        } catch (\Exception $e) {
            Log::error('Error in completeTransaction: ' . $e->getMessage()); // Debug log
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
