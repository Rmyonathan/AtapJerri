<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use App\Models\Panel;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

use Exception;

class PanelController extends Controller
{
    /**
     * Display inventory summary
     */
    protected $stockController;

    public function __construct(StockController $stockController)
    {
        $this->stockController = $stockController;
    }

    public function inventory()
    {
        $inventory = $this->getInventorySummary();
        return view('panels.repack', compact('inventory'));
    }

    public function viewBarang(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = 10; // Number of items per page

        $inventory = $this->getKodeSummary($search, $perPage);
        return view('master.barang', compact('inventory', 'search'));
    }

    /**
     * Display the repack/potong view with cutting history
     */
    public function repack(Request $request)
    {
        // Get date filter or default to today
        $dateFilter = $request->input('date_filter');

        // Get all orders with their related order items and panels
        $cuttingHistory = Order::with(['orderItems.panel'])
            ->when($dateFilter, function($query) use ($dateFilter) {
                return $query->whereDate('created_at', $dateFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Get current inventory summary
        $inventory = $this->getInventorySummary();

        return view('panels.repack', [
            'cuttingHistory' => $cuttingHistory,
            'inventory' => $inventory
        ]);
    }

    /**
     * Show the form for creating a new panel order
     */
    public function createOrder()
    {
        $panel = KodeBarang::all();
        $inventory = $this->getInventorySummary();
        return view('panels.order', compact('inventory', 'panel'));
    }

    public function searchAvailablePanels(Request $request){
        $keyword = $request->get('keyword');
        // $so = $request->get('so', 'LAMPUNG'); // default SO, bisa diganti sesuai kebutuhan

        // Cari panel yang cocok dengan keyword
        $panels = Panel::where(function($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('group_id', 'like', "%{$keyword}%");
            })
            ->orderBy('group_id')
            ->get()
            ->groupBy(function ($item) {
                return $item->group_id . '|' . $item->name;
            })
            ->map(function ($groupedItems) {
                return $groupedItems->first();
            })
            ->values();

        // Filter hanya panel yang stoknya > 0 di tabel stocks
        $filtered = $panels->filter(function($panel)  {
            $stock = \App\Models\Stock::where('kode_barang', $panel->group_id)
                ->first();
            return $stock && $stock->good_stock > 0;
        });

        return response()->json($filtered->values());
    }

    /**
     * Search for panels by name or ID
     */
    public function search(Request $request){
        $keyword = $request->get('keyword');

        $panels = Panel::where('name', 'like', "%{$keyword}%")
            ->orWhere('group_id', 'like', "%{$keyword}%")
            ->orderBy('group_id')
            ->get()
            ->groupBy(function ($item) {
                return $item->group_id . '|' . $item->name;
            })
            ->map(function ($groupedItems) {
                return $groupedItems->first();
            })
            ->values();

        return response()->json($panels);
    }

    public function storeOrder(Request $request)
    {
        // Validate each panel input
        $validated = $request->validate([
            'panels' => 'required|array|min:1',
            'panels.*.name' => 'required|string|max:255',
            'panels.*.length' => 'required|numeric|min:0.1',
            'panels.*.quantity' => 'required|integer|min:1',
        ], [
            'panels.required' => 'At least one panel order is required.',
            'panels.*.name.required' => 'Panel name is required',
            'panels.*.name.string' => 'Panel name must be a valid string',
            'panels.*.name.max' => 'Panel name may not be greater than 255 characters',
            'panels.*.length.required' => 'Panel length is required',
            'panels.*.length.numeric' => 'Panel length must be a number',
            'panels.*.length.min' => 'Panel length must be at least 0.1 meters',
            'panels.*.quantity.required' => 'Quantity is required',
            'panels.*.quantity.integer' => 'Quantity must be a whole number',
            'panels.*.quantity.min' => 'Quantity must be at least 1',
        ]);

        $successMessages = [];
        $errors = [];

        foreach ($validated['panels'] as $panel) {
            $result = $this->processOrder(
                $panel['name'],
                $panel['length'],
                $panel['quantity']
            );

            if ($result['success']) {
                $successMessages[] = $result['message'];
            } else {
                $errors[] = $result['message'];
            }
        }

        if (!empty($errors)) {
            return back()->with('error', implode(' | ', $errors))->withInput();
        }

        return redirect()->route('panels.repack')
            ->with('success', implode(' | ', $successMessages));
    }

    /**
     * Show the form for adding new panels to inventory
     */
    public function createInventory()
    {
        $codes = KodeBarang::all();

        return view('panels.add-inventory', compact('codes'));
    }

    public function editInventory(Request $request)
    {
        $panel = KodeBarang::where('kode_barang', $request->id)->first();
        $quantity = Panel::where('group_id', $request->id)->count();
        return view('panels.edit', [
            'panel' => $panel,
            'quantity' => $quantity
        ]);
    }


    /**
     * Add new panels to inventory
     */
    public function storeInventory(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_id' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ], [
            'group_id.required' => 'Item code is required',
            'group_id.string' => 'Item code must be a valid string',
            'group_id.max' => 'Item code may not be greater than 255 characters',

            'name.required' => 'Panel name is required',
            'name.string' => 'Panel name must be a valid string',
            'name.max' => 'Panel name may not be greater than 255 characters',

            'cost.required' => 'Cost is required',
            'cost.numeric' => 'Cost must be a valid number',
            'cost.min' => 'Cost must be at least 0',

            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price must be at least 0',

            'quantity.required' => 'Quantity is required',
            'quantity.integer' => 'Quantity must be a whole number',
            'quantity.min' => 'Quantity must be at least 1',
        ]);

        $name = $validated['name'];
        $group_id = $validated['group_id'];
        $length = KodeBarang::where('kode_barang', $group_id)->first()->length;
        $cost = $validated['cost'];
        $price = $validated['price'];
        $quantity = $validated['quantity'];

        $check_panel = Panel::where('group_id', $group_id)->where('length', $length)->exists();

        if ($check_panel) {
            return response()->json(['error' => 'Panel already exists.'], 400);
        }

        $result = $this->addPanelsToInventory($name, $cost, $price, $length, $group_id, $quantity);

        return redirect()->route('panels.inventory')
            ->with('success', $result['message']);
    }

    public function updateInventory(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_id' => 'required|string|max:255',
            'length' => 'required|numeric|min:0.1',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'status' => 'required',
            'quantity' => 'required|integer|min:1',
        ], [
            'group_id.required' => 'Item code is required',
            'group_id.string' => 'Item code must be a valid string',
            'group_id.max' => 'Item code may not be greater than 255 characters',

            'name.required' => 'Panel name is required',
            'name.string' => 'Panel name must be a valid string',
            'name.max' => 'Panel name may not be greater than 255 characters',

            'length.required' => 'Panel length is required',
            'length.numeric' => 'Panel length must be a number',
            'length.min' => 'Panel length must be at least 0.1 meters',

            'cost.required' => 'Cost is required',
            'cost.numeric' => 'Cost must be a valid number',
            'cost.min' => 'Cost must be at least 0',

            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price must be at least 0',

            'quantity.required' => 'Quantity is required',
            'quantity.integer' => 'Quantity must be a whole number',
            'quantity.min' => 'Quantity must be at least 1',
        ]);

        $name = $validated['name'];
        $length = $validated['length'];
        $group_id = $validated['group_id'];
        $cost = $validated['cost'];
        $price = $validated['price'];
        $quantity = $validated['quantity'];
        $status = $validated['status'];

        Panel::where('group_id', $group_id)->delete();

        $kode = KodeBarang::where('kode_barang', $group_id)->first();
        $kode->name = $name;
        $kode->length = $length;
        $kode->cost = $cost;
        $kode->price = $price;
        $kode->status = $status;
        $kode->save();

        // $parts = explode('-', $group_id);
        // $group_id = $parts[0];

        $result = $this->addPanelsToInventory($name, $cost, $price, $length, $group_id, $quantity);

        return redirect()->route('master.barang')
            ->with('success', $result['message']);
    }

    public function deleteInventory(Request $request)
    {
        Panel::where('group_id', $request->id)->delete();

        return redirect()->route('master.barang')
            ->with('success', "Item deleted successfully");
    }

    /**
     * Process an order for aluminum panels
     *
     * @param float $requestedLength Length of panel requested (in meters)
     * @param int $requestedQuantity Quantity of panels requested
     * @return array Status of the operation
     */
    // public function repackOrder(Request $request) {
    //     // Validate input fields
    //     $validated = $request->validate([
    //     'penambah' => 'required|string|max:255',
    //     'pengurang' => 'required|string|max:255',
    //     'quantity' => 'required|integer|min:1',
    //     ], [
    //         'penambah.required' => 'Item code is required',
    //         'penambah.string' => 'Item code must be a valid string',
    //         'penambah.max' => 'Item code may not be greater than 255 characters',

    //         //Pengurang and quantity convert it to accepting arrays
    //         'pengurang.required' => 'Item code is required',
    //         'pengurang.string' => 'Item code must be a valid string',
    //         'pengurang.max' => 'Item code may not be greater than 255 characters',

    //         'quantity.required' => 'Quantity is required',
    //         'quantity.integer' => 'Quantity must be a whole number',
    //         'quantity.min' => 'Quantity must be at least 1',
    //     ]);

    //     // Get the codes
    //     $penambah = KodeBarang::where('kode_barang', $validated['penambah'])->first();
    //     $pengurang = KodeBarang::where('kode_barang', $validated['pengurang'])->first(); //Turn this into foreach. So for each pengurang, get their datas.
    //     $qty = $validated['quantity'];

    //     // Add debugging
    //     // \Log::info('Processing order with data:', [
    //     //     'penambah' => $penambah->kode_barang,
    //     //     'penambah_length' => $penambah->length,
    //     //     'pengurang' => $pengurang->kode_barang,
    //     //     'pengurang_length' => $pengurang->length,
    //     //     'qty' => $qty
    //     // ]);

    //     // Perform validation with improved parentheses for clarity
    //     // In this validation, turn it into sum of (pengurang->length * quantity)
    //     if (
    //         // Case 1: Penambah is longer but doesn't divide evenly
    //         (($penambah->length >= ($pengurang->length * $qty)) &&
    //         ($penambah->length - ($pengurang->length * $qty) != 0))
    //         ||
    //         // Case 2: Penambah is shorter but doesn't fit evenly
    //         (($penambah->length < ($pengurang->length * $qty)) &&
    //         (($pengurang->length * $qty) % $penambah->length != 0))
    //         ||
    //         (($qty * $pengurang->length) > (Panel::where('group_id', $penambah->kode_barang)->where('available', True)->count() * $penambah->length))
    //     ) {
    //         // Add debugging
    //         Log::warning('Invalid conversion ratio detected', [
    //             'calculation1' => ($penambah->length >= ($pengurang->length * $qty)),
    //             'calculation2' => ($penambah->length - ($pengurang->length * $qty) != 0),
    //             'calculation3' => ($penambah->length < ($pengurang->length * $qty)),
    //             'calculation4' => (($pengurang->length * $qty) % $penambah->length != 0),
    //         ]);

    //         if ($request->ajax() || $request->wantsJson()) {
    //             return response()->json(['error' => 'Invalid conversion ratio.'], 400);
    //         } else {
    //             return redirect()->back()->with('error', 'Invalid conversion ratio.');
    //         }
    //     } else {
    //         $reduction = 0;
    //         if ($penambah->length - ($pengurang->length * $qty) == 0) {
    //             $reduction = 1;
    //         } else {
    //             $reduction = ($qty * $pengurang->length) / $penambah->length;
    //         }

    //         $panelPenambah = Panel::where('group_id', $penambah->kode_barang)
    //                         ->where('available', true)
    //                         ->limit((int) $reduction)
    //                         ->get();

    //         // Create order record for tracking
    //         $order = Order::create([
    //             'total_quantity' => $qty,
    //             'name' => $pengurang->name,
    //             'transaction' => $pengurang->price * $pengurang->length * $qty,
    //             'total_length' => $qty * $pengurang->length,
    //             'status' => 'completed',
    //             'notes' => "Repack from {$penambah->kode_barang} to {$pengurang->kode_barang}"
    //         ]);

    //         foreach ($panelPenambah as $p) {
    //             $p->available = false;
    //             $p->save();

    //             // Create order item with more detailed information
    //             OrderItem::create([
    //                 'order_id' => $order->id,
    //                 'panel_id' => $p->id,
    //                 'name' => $pengurang->name . " (from " . $penambah->name . ")",
    //                 'length' => $pengurang->length,
    //                 'transaction' => $pengurang->price * $pengurang->length * $qty,
    //                 'original_panel_length' => $p->length,
    //                 'remaining_length' => 0
    //             ]);
    //         }

    //         //This also turn it into for each until the addpanels to inventory
    //         $name = $pengurang->name;
    //         $price = $pengurang->price;
    //         $cost = $pengurang->cost;
    //         $length = $pengurang->length;
    //         $group_id = $pengurang->kode_barang;

    //         $this->addPanelsToInventory($name, $cost, $price, $length, $group_id, $qty);

    //         if ($request->ajax() || $request->wantsJson()) {
    //             return response()->json([
    //                 'success' => true,
    //                 'message' => "Successfully processed order for {$qty} panels of {$request->pengurang}."
    //             ]);
    //         } else {
    //             return redirect()->route('panels.repack')
    //                 ->with('success', "Successfully processed order for {$qty} panels of {$request->pengurang}.");
    //         }
    //     }
    // }

    public function repackOrder(Request $request)
    {
        // Validate input fields (now accepting arrays for pengurang and quantity)
        $validated = $request->validate([
            'penambah' => 'required|string|max:255',
            'pengurang' => 'required|array|min:1',
            'pengurang.*' => 'required|string|max:255',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|integer|min:1',
        ], [
            'penambah.required' => 'Item code is required',
            'pengurang.required' => 'At least one item to reduce is required',
            'quantity.required' => 'Quantities are required',
            'pengurang.*.required' => 'Each item code is required',
            'pengurang.*.string' => 'Each item code must be a valid string',
            'pengurang.*.max' => 'Each item code may not be greater than 255 characters',
            'quantity.*.integer' => 'Each quantity must be a whole number',
            'quantity.*.min' => 'Each quantity must be at least 1',
        ]);

        $penambah = KodeBarang::where('kode_barang', $validated['penambah'])->first();
        $pengurangs = $validated['pengurang'];
        $quantities = $validated['quantity'];
        $frequency = $request->frequency ?? 1;

        for ($j = 0; $j < $frequency; $j++) {
            $totalPengurangLength = 0;
            $totalTransaction = 0;
            $totalQty = 0;
            $pengurangData = [];

            foreach ($pengurangs as $index => $kode) {
                $pengurang = KodeBarang::where('kode_barang', $kode)->first();
                $qty = $quantities[$index];

                $totalPengurangLength += $pengurang->length * $qty;
                $totalTransaction += $pengurang->price * $pengurang->length * $qty;
                $totalQty += $qty;

                $pengurangData[] = [
                    'item' => $pengurang,
                    'qty' => $qty,
                ];
            }

            // Check invalid conversion or insufficient inventory
            if (
                ($penambah->length >= $totalPengurangLength && ($penambah->length - $totalPengurangLength) != 0)
                || ($penambah->length < $totalPengurangLength && ($totalPengurangLength % $penambah->length != 0))
                || ($totalPengurangLength * $frequency > (Panel::where('group_id', $penambah->kode_barang)->where('available', true)->count() * $penambah->length * $frequency))
            ) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['error' => 'Invalid conversion ratio.'], 400);
                } else {
                    return redirect()->back()->with('error', 'Invalid conversion ratio.');
                }
            }
            // Calculate how many panels of penambah are needed
            $reduction = ($penambah->length - $totalPengurangLength == 0) ? 1 : ($totalPengurangLength / $penambah->length);

            $panelPenambah = Panel::where('group_id', $penambah->kode_barang)
                ->where('available', true)
                ->limit((int) $reduction)
                ->get();

            // Create main order
            $order = Order::create([
                'total_quantity' => $totalQty,
                'name' => 'Multi-Item Repack',
                'transaction' => $totalTransaction,
                'total_length' => $totalPengurangLength,
                'status' => 'completed',
                'notes' => "Repack from {$penambah->kode_barang} to multiple items"
            ]);

            // Record stock reduction for penambah (the source material)
            $this->stockController->recordSale(
                $penambah->kode_barang,
                $penambah->name,
                'REPACK-FROM-' . date('YmdHis'),
                now(),
                'REPACK-' . $order->id,
                'REPACK PROCESS',
                $reduction, // We're using this many panels
                'LBR', // Satuan
                "Source material for repack to multiple items",
                null, // created_by (optional)
                'default' // SO
            );

            foreach ($panelPenambah as $p) {
                $p->available = false;
                $p->save();
            }

            // For each pengurang, create OrderItems and inventory
            foreach ($pengurangData as $entry) {
                $item = $entry['item'];
                $qty = $entry['qty'];

                for ($i = 0; $i < $qty; $i++) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'panel_id' => $panelPenambah[0]->id ?? null, // Optional: random assignment
                        'name' => $item->name . " (from " . $penambah->name . ")",
                        'length' => $item->length,
                        'transaction' => $item->price * $item->length,
                        'original_panel_length' => $penambah->length,
                        'remaining_length' => 0
                    ]);
                }

                // Add panels to inventory
                $this->addPanelsToInventory(
                    $item->name,
                    $item->cost,
                    $item->price,
                    $item->length,
                    $item->kode_barang,
                    $qty
                );

                // Record stock increase for pengurang (the resulting material)
                $this->stockController->recordPurchase(
                    $item->kode_barang,
                    $item->name,
                    'REPACK-TO-' . date('YmdHis'),
                    now(),
                    'REPACK-' . $order->id,
                    'REPACK PROCESS',
                    $qty, // Quantity of new items created
                    'LBR', // Satuan
                    "Result from repack of {$penambah->kode_barang}",
                    null, // created_by (optional)
                    'default' // SO
                );
            }
        }

        $successMessage = "Successfully processed multi-item repack.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage
            ]);
        } else {
            return redirect()->route('panels.repack')->with('success', $successMessage);
        }
    }


    /**
     * View receipt for a specific order
     */

    public function printReceipt($orderId)
    {
        $order = Order::with(['orderItems.panel'])->findOrFail($orderId);

        return view('panels.receipt', compact('order'));
    }

    /**
     * View details for a specific order
     */
    public function viewOrder($orderId)
    {
        $order = Order::with('orderItems.panel')->findOrFail($orderId);

        return view('panels.order-details', compact('order'));
    }

    private function processOrder(string $requestedName, float $requestedLength, int $requestedQuantity): array
    {
        // Start a database transaction using Eloquent model
        try {
            // Using Laravel's automatic transaction handling with a callback
            $result = $this->executeWithTransaction(function() use ($requestedName, $requestedLength, $requestedQuantity) {
                $remainingQuantity = $requestedQuantity;
                $usedPanels = [];

                // First try to use exact-sized panels if available
                $exactPanels = Panel::where('length', $requestedLength)
                    ->where('available', true)
                    ->where('name', $requestedName)
                    ->orderBy('id')
                    ->limit($remainingQuantity)
                    ->get();

                // If we still need more panels, look for longer ones to cut
                if ($remainingQuantity > 0) {
                    // Find panels longer than requested size, sorted by length (use smallest suitable panels first)
                    $longerPanels = Panel::where('length', '>', $requestedLength)
                        ->where('name', $requestedName)
                        ->where('available', true)
                        ->orderBy('length')
                        ->get();

                    foreach ($longerPanels as $panel) {
                        if ($remainingQuantity <= 0) {
                            break;
                        }

                        $originalLength = $panel->length;
                        $remainingLength = $originalLength;

                        // Mark the panel as used
                        $panel->available = false;
                        $panel->save();

                        // Record stock mutation for the used panel (decrease)
                        $this->stockController->recordSale(
                            $panel->group_id,
                            $panel->name,
                            'CUT-' . date('YmdHis') . '-' . $panel->id,
                            now(),
                            'PANEL-CUT-' . $panel->id,
                            'PANEL CUTTING',
                            1, // Quantity is 1 panel
                            'LBR', // Satuan
                            "Used {$originalLength}m panel for cutting", // Keterangan
                            null, // created_by (optional)
                            'default' // SO now at the end
                        );

                        // Cut as many times as possible from this panel
                        while ($remainingLength >= $requestedLength && $remainingQuantity > 0) {
                            $remainingLength -= $requestedLength;
                            $remainingQuantity--;

                            $usedPanels[] = [
                                'panel_id' => $panel->id,
                                'name' => $panel->name,
                                'price' => $panel->price,
                                'original_length' => $originalLength,
                                'used_length' => $requestedLength,
                                'remaining_length' => $remainingLength
                            ];

                            // Create new panel with requested length
                            $newPanel = $this->createPanel($requestedName, $panel->cost, $panel->price, $requestedLength, true, $panel->id);

                            // Record stock mutation for the new requested length panel (increase)
                            $newPanelObj = $newPanel['panels'];
                            $this->stockController->recordPurchase(
                                $newPanelObj->group_id,
                                $newPanelObj->name,
                                'CUT-NEW-' . date('YmdHis') . '-' . $newPanelObj->id,
                                now(),
                                'PANEL-CUT-NEW-' . $newPanelObj->id,
                                'PANEL CUTTING - NEW',
                                1, // Quantity is 1 panel
                                'ALUMKA',
                                'LBR',
                                "Created {$requestedLength}m panel from cutting"
                            );
                        }

                        // Create a new panel for leftover length if usable
                        if ($remainingLength >= 0.5) {
                            $remainingPanel = $this->createPanel($requestedName, $panel->cost, $panel->price, $remainingLength, true, $panel->id);

                            // Record stock mutation for the remaining length panel (increase)
                            $remainingPanelObj = $remainingPanel['panels'];
                            // Change to:
                            $this->stockController->recordPurchase(
                                $newPanelObj->group_id,
                                $newPanelObj->name,
                                'CUT-NEW-' . date('YmdHis') . '-' . $newPanelObj->id,
                                now(),
                                'PANEL-CUT-NEW-' . $newPanelObj->id,
                                'PANEL CUTTING - NEW',
                                1, // Quantity is 1 panel
                                'LBR', // Satuan
                                "Created {$requestedLength}m panel from cutting", // Keterangan
                                null, // created_by (optional)
                                'default' // SO now at the end
                            );
                        }
                    }
                }

                // Check if we fulfilled the entire order
                if ($remainingQuantity > 0) {
                    // Signal that we need to rollback by throwing an exception
                    throw new Exception("Insufficient inventory. Short by {$remainingQuantity} panels of {$requestedLength}m length.");
                }

                $selectedPanel = Panel::where('name', $requestedName)->first();
                // Create order record
                $order = Order::create([
                    'total_quantity' => $requestedQuantity,
                    'name' => $requestedName,
                    'transaction' => $selectedPanel->price * $requestedLength * $requestedQuantity,
                    'total_length' => $requestedQuantity * $requestedLength,
                    'status' => 'completed'
                ]);

                // Create order items for tracking which panels were used
                foreach ($usedPanels as $usedPanel) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'panel_id' => $usedPanel['panel_id'],
                        'name' => $requestedName,
                        'length' => $requestedLength,
                        'transaction' => $usedPanel['price'] * $requestedLength * $requestedQuantity,
                        'original_panel_length' => $usedPanel['original_length'],
                        'remaining_length' => $usedPanel['remaining_length']
                    ]);
                }

                return [
                    'success' => true,
                    'message' => "Successfully processed order for {$requestedQuantity} panels of {$requestedLength}m length.",
                    'order_id' => $order->id,
                    'used_panels' => $usedPanels
                ];
            });

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'fulfilled' => 0
            ];
        }
    }

    /**
     * Execute a callback within a transaction
     *
     * @param callable $callback
     * @return mixed
     */
    private function executeWithTransaction(callable $callback)
    {
        // Get the connection from an Eloquent model
        $connection = app('db')->connection();

        try {
            $connection->beginTransaction();
            $result = $callback();
            $connection->commit();
            return $result;
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Get current inventory summary
     *
     * @return array Inventory summary grouped by length
     */
    private function getInventorySummary(): array
    {
        // Using Eloquent to get all available panels
        $panels = Panel::where('available', True)->get();

        // Manually group the panels
        $groupedPanels = [];
        foreach ($panels as $panel) {
            $key = $panel->length . '-' . $panel->name . '-' . $panel->price . '-' . $panel->group_id;

            if (!isset($groupedPanels[$key])) {
                $groupedPanels[$key] = [
                    'id' => $panel->id,
                    'length' => $panel->length,
                    'name' => $panel->name,
                    'cost' => $panel->cost,
                    'price' => $panel->price,
                    'group_id' => $panel->group_id,
                    'quantity' => 1
                ];
            } else {
                $groupedPanels[$key]['quantity']++;
            }
        }

        // Convert to array values
        $inventory = array_values($groupedPanels);

        // Sort by length
        usort($inventory, function($a, $b) {
            return $a['length'] <=> $b['length'];
        });

        return [
            'total_panels' => count($panels),
            'inventory_by_length' => $inventory
        ];
    }

    public function getKodeSummary($search = '', $perPage = 10): array
    {
        // Query builder for KodeBarang with search filter
        $query = KodeBarang::query();

        // Apply search filter if search term exists
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('kode_barang', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('attribute', 'like', "%{$search}%");
            });
        }

        // Paginate the results
        $panelsPaginator = $query->paginate($perPage);

        // Get the data from the paginator
        $panels = $panelsPaginator->items();

        // Manually group the panels
        $groupedPanels = [];
        foreach ($panels as $panel) {
            $key = $panel->length . '-' . $panel->name . '-' . $panel->price . '-' . $panel->kode_barang;

            if (!isset($groupedPanels[$key])) {
                $groupedPanels[$key] = [
                    'id' => $panel->id,
                    'length' => $panel->length,
                    'name' => $panel->name,
                    'cost' => $panel->cost,
                    'price' => $panel->price,
                    'group_id' => $panel->kode_barang,
                    'group' => $panel->attribute,
                    'status' => $panel->status,
                    'quantity' => Panel::where('group_id', $panel->kode_barang)->where('available', True)->count()
                ];
            } else {
                $groupedPanels[$key]['quantity']++;
            }
        }

        // Convert to array values
        $inventory = array_values($groupedPanels);

        // Sort by length
        usort($inventory, function($a, $b) {
            return $a['length'] <=> $b['length'];
        });

        return [
            'total_panels' => $panelsPaginator->total(),
            'inventory_by_length' => $inventory,
            'paginator' => $panelsPaginator // Pass the paginator object for use in the view
        ];
    }

    public function getPanelByKodeBarang(Request $request)
    {
        $kodeBarang = $request->input('kode_barang');

        // Get the KodeBarang model to get its name
        $kodeBarangModel = KodeBarang::where('kode_barang', $kodeBarang)->first();

        if ($kodeBarangModel) {
            return response()->json([
                'success' => true,
                'panel_name' => $kodeBarangModel->name // Use KodeBarang's name field
            ]);
        }

        return response()->json([
            'success' => false,
            'panel_name' => null
        ]);
    }

    /**
     * Add new panels to inventory
     *
     * @param float $length Length of panels in meters
     * @param int $quantity Number of panels to add
     * @return array Status of the operation
     */
    public function addPanelsToInventory(string $name, float $cost, float $price, float $length, string $group_id, int $quantity): array
    {
        $panels = [];

        for ($i = 0; $i < $quantity; $i++) {
            $panel = $this->createPanel($name, $cost, $price, $length, true, null, $group_id);
            $panels[] = $panel;
        }

        return [
            'success' => true,
            'message' => "Added {$quantity} panels of {$length}m length to inventory.",
            'panels' => $panels
        ];
    }

    private function createPanel(string $name, float $cost, float $price, float $length, bool $available = true, ?int $parent_panel_id = NULL, ?string $group_id = NULL): array
    {
        $panel = Panel::create([
            'name' => $name,
            'cost' => $cost,
            'price' => $price,
            'length' => $length,
            'group_id' => $group_id,
            'available' => $available,
            'parent_panel_id' => $parent_panel_id
        ]);

        if ($panel->group_id == NULL){
            $getAttribute = Panel::find($parent_panel_id);
            $getAttribute = $getAttribute->group_id;
            $getAttribute = KodeBarang::where('kode_barang', $getAttribute)->first();

            $getNewName = KodeBarang::where('attribute', $getAttribute->attribute)
                                 ->where('length', $length)->first();
            $getNewName = Panel::where('group_id', $getNewName->kode_barang)->first();
            $new_name = $getNewName->name;
            $getAttribute = $getAttribute->attribute;
            $getCode = KodeBarang::where('attribute', $getAttribute)
                              ->where('length', $length)
                              ->first();
            $panel->name = $new_name;
            $panel->group_id = $getCode->kode_barang;
            $panel->save();
        }

        return [
            'panels' => $panel
        ];
    }
}
