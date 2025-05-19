@extends('layout.Nav')

@section('content')
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-cut mr-2"></i>Order Aluminum Panel</h2>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">New Panel Order</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Penambah Selection (Common for all orders) -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-plus-circle mr-1"></i> Select Penambah Code</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="searchPenambah"><i class="fas fa-search mr-1"></i> Penambah Code</label>
                                <div class="input-group">
                                    <input type="text" id="searchPenambah" class="form-control" placeholder="Search penambah code...">
                                    <div class="input-group-append">
                                        <button type="button" id="searchPenambahBtn" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="penambahDropdownContainer" class="position-relative">
                                    <div id="penambahDropdown" class="dropdown-menu w-100" style="display: none;"></div>
                                </div>
                            </div>
                            <div id="selectedPenambahDisplay" class="alert alert-info d-none">
                                <i class="fas fa-info-circle mr-1"></i> Selected Penambah: <strong id="selectedPenambahText"></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Pengurang Selection -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-minus-circle mr-1"></i> Add Pengurang Codes</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="searchPengurang"><i class="fas fa-search mr-1"></i> Pengurang Code</label>
                                <div class="input-group">
                                    <input type="text" id="searchPengurang" class="form-control" placeholder="Search pengurang code...">
                                    <div class="input-group-append">
                                        <button type="button" id="searchPengurangBtn" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="pengurangDropdownContainer" class="position-relative">
                                    <div id="pengurangDropdown" class="dropdown-menu w-100" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="quantity"><i class="fas fa-layer-group mr-1"></i> Quantity</label>
                                <input type="number" id="quantity" class="form-control" min="1" value="1">
                            </div>

                            <button type="button" id="addToTableBtn" class="btn btn-success">
                                <i class="fas fa-plus mr-1"></i> Add to Table
                            </button>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-table mr-1"></i> Order Items</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Pengurang Code</th>
                                            <th>Description</th>
                                            <th>Quantity</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="orderItemsTable">
                                        <tr id="emptyTableRow">
                                            <td colspan="4" class="text-center text-muted">No items added yet</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="frequencyInput"><i class="fas fa-layer-group mr-1"></i>Berapa kali Anda ingin memotong? </label>
                        <input type="number" id="frequencyInput" class="form-control" min="1" value="1">
                    </div>

                    <!-- Process Order Button -->
                    <button type="button" id="processOrderBtn" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-cart mr-1"></i> Process Order(s)
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle mr-1"></i> Current Inventory</h5>
                </div>
                <div class="card-body">
                    @if(isset($inventory) && count($inventory['inventory_by_length']) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Length (m)</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($inventory['inventory_by_length'] as $item)
                                        <tr>
                                            <td>{{ number_format($item['length'], 2) }}</td>
                                            <td>{{ $item['quantity'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-boxes mr-1"></i> Total Available Panels: <strong>{{ $inventory['total_panels'] }}</strong>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i> No panels currently in inventory.
                        </div>
                        <a href="{{ route('panels.create-inventory') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Add Panels to Inventory
                        </a>
                    @endif
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lightbulb mr-1"></i> How It Works</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item">
                            <i class="fas fa-check text-success mr-2"></i> The system first uses panels with exact requested length
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-check text-success mr-2"></i> If needed, longer panels will be cut to the requested size
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-check text-success mr-2"></i> Remaining cut pieces are added back to inventory
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Penambah Code Search Modal -->
<div class="modal fade" id="penambahSearchModal" tabindex="-1" role="dialog" aria-labelledby="penambahSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="penambahSearchModalLabel"><i class="fas fa-search mr-1"></i> Search Penambah Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="penambahModalInput" class="form-control" placeholder="Enter code to search...">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="searchPenambahModalBtn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Length</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="penambahSearchResults">
                            <!-- Search results will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pengurang Code Search Modal -->
<div class="modal fade" id="pengurangSearchModal" tabindex="-1" role="dialog" aria-labelledby="pengurangSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pengurangSearchModalLabel"><i class="fas fa-search mr-1"></i> Search Pengurang Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="pengurangModalInput" class="form-control" placeholder="Enter code to search...">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="searchPengurangModalBtn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Length</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="pengurangSearchResults">
                            <!-- Search results will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- <!-- Hidden form to submit orders -->
<form id="orderSubmitForm" action="{{ route('panels.store-order') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="penambah" id="formPenambah">
    <input type="hidden" name="pengurang" id="formPengurang">
    <input type="hidden" name="quantity" id="formQuantity">
</form> --}}
<form id="orderSubmitForm" action="{{ route('panels.store-order') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="penambah" id="formPenambah">
    <!-- Container for pengurang array inputs -->
    <div id="pengurangContainer"></div>
    <!-- Container for quantity array inputs -->
    <div id="quantityContainer"></div>
    <input type="hidden" name="frequency" id="formFrequency">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    let selectedPenambah = null;
    let orderItems = [];

    // DOM Elements
    const searchPenambahInput = document.getElementById('searchPenambah');
    const searchPenambahBtn = document.getElementById('searchPenambahBtn');
    const searchPengurangInput = document.getElementById('searchPengurang');
    const searchPengurangBtn = document.getElementById('searchPengurangBtn');
    const quantityInput = document.getElementById('quantity');
    const addToTableBtn = document.getElementById('addToTableBtn');
    const orderItemsTable = document.getElementById('orderItemsTable');
    const processOrderBtn = document.getElementById('processOrderBtn');
    const selectedPenambahDisplay = document.getElementById('selectedPenambahDisplay');
    const selectedPenambahText = document.getElementById('selectedPenambahText');

    const frequencyInput = document.getElementById('frequencyInput');

    // Open search modals
    searchPenambahBtn.addEventListener('click', function() {
        $('#penambahSearchModal').modal('show');
    });

    searchPengurangBtn.addEventListener('click', function() {
        $('#pengurangSearchModal').modal('show');
    });

    // Modal search buttons
    document.getElementById('searchPenambahModalBtn').addEventListener('click', function() {
        const keyword = document.getElementById('penambahModalInput').value;
        searchCodes(keyword, 'penambahSearchResults', 'select-penambah');
    });

    document.getElementById('searchPengurangModalBtn').addEventListener('click', function() {
        const keyword = document.getElementById('pengurangModalInput').value;
        searchCodes(keyword, 'pengurangSearchResults', 'select-pengurang');
    });

    // Input search handlers
    searchPenambahInput.addEventListener('input', function() {
        const keyword = this.value;
        if (keyword.length >= 2) {
            searchCodesDropdown(keyword, 'penambah');
        } else {
            document.getElementById('penambahDropdown').style.display = 'none';
        }
    });

    searchPengurangInput.addEventListener('input', function() {
        const keyword = this.value;
        if (keyword.length >= 2) {
            searchCodesDropdown(keyword, 'pengurang');
        } else {
            document.getElementById('pengurangDropdown').style.display = 'none';
        }
    });

    // Function to search codes for modals
    function searchCodes(keyword, resultsElementId, selectClass) {
        if (keyword.length > 0) {
            // This would normally be an AJAX request to your backend
            // For demonstration, we'll use the panel data available in the template
            const panels = @json($panel ?? []);

            let html = '';
            const matchingPanels = panels.filter(panel =>
                panel.kode_barang.toLowerCase().includes(keyword.toLowerCase())
            );

            if (matchingPanels.length > 0) {
                matchingPanels.forEach(panel => {
                    html += `<tr>
                        <td>${panel.kode_barang}</td>
                        <td>${panel.attribute || 'N/A'}</td>
                        <td>${panel.length || 'N/A'} m</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary ${selectClass}"
                                data-kode="${panel.kode_barang}"
                                data-name="${panel.attribute || ''}"
                                data-length="${panel.length || 0}">
                                <i class="fas fa-check"></i> Select
                            </button>
                        </td>
                    </tr>`;
                });
            } else {
                html = '<tr><td colspan="4" class="text-center">No matching codes found</td></tr>';
            }

            document.getElementById(resultsElementId).innerHTML = html;

            // Add event listeners to select buttons
            document.querySelectorAll(`.${selectClass}`).forEach(button => {
                button.addEventListener('click', function() {
                    const kode = this.getAttribute('data-kode');
                    const name = this.getAttribute('data-name');
                    const length = this.getAttribute('data-length');

                    if (selectClass === 'select-penambah') {
                        // Set penambah
                        selectedPenambah = {
                            kode: kode,
                            name: name,
                            length: length
                        };
                        searchPenambahInput.value = kode;
                        selectedPenambahDisplay.classList.remove('d-none');
                        selectedPenambahText.textContent = `${kode} - ${name} (${length} m)`;
                        $('#penambahSearchModal').modal('hide');
                    } else {
                        // Set pengurang
                        searchPengurangInput.value = kode;
                        searchPengurangInput.setAttribute('data-name', name);
                        searchPengurangInput.setAttribute('data-length', length);
                        $('#pengurangSearchModal').modal('hide');
                    }
                });
            });
        }
    }

    // Function to create dropdown results for direct search
    function searchCodesDropdown(keyword, type) {
        // This would normally be an AJAX request
        const panels = @json($panel ?? []);

        const matchingPanels = panels.filter(panel =>
            panel.kode_barang.toLowerCase().includes(keyword.toLowerCase())
        );

        const dropdownId = `${type}Dropdown`;
        const dropdown = document.getElementById(dropdownId);

        if (matchingPanels.length > 0) {
            let html = '';
            matchingPanels.forEach(panel => {
                html += `<a class="dropdown-item ${type}-dropdown-item"
                            data-kode="${panel.kode_barang}"
                            data-name="${panel.attribute || ''}"
                            data-length="${panel.length || 0}">
                        ${panel.kode_barang} - ${panel.attribute || 'N/A'} (${panel.length || 'N/A'} m)
                        </a>`;
            });
            dropdown.innerHTML = html;
            dropdown.style.display = 'block';

            // Add event listeners to dropdown items
            document.querySelectorAll(`.${type}-dropdown-item`).forEach(item => {
                item.addEventListener('click', function() {
                    const kode = this.getAttribute('data-kode');
                    const name = this.getAttribute('data-name');
                    const length = this.getAttribute('data-length');

                    if (type === 'penambah') {
                        selectedPenambah = {
                            kode: kode,
                            name: name,
                            length: length
                        };
                        searchPenambahInput.value = kode;
                        selectedPenambahDisplay.classList.remove('d-none');
                        selectedPenambahText.textContent = `${kode} - ${name} (${length} m)`;
                    } else {
                        searchPengurangInput.value = kode;
                        searchPengurangInput.setAttribute('data-name', name);
                        searchPengurangInput.setAttribute('data-length', length);
                    }

                    dropdown.style.display = 'none';
                });
            });
        } else {
            dropdown.innerHTML = '<a class="dropdown-item disabled">No matching codes found</a>';
            dropdown.style.display = 'block';
        }
    }

    // Hide dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#searchPenambah') && !e.target.closest('#penambahDropdown')) {
            const dropdown = document.getElementById('penambahDropdown');
            if (dropdown) dropdown.style.display = 'none';
        }

        if (!e.target.closest('#searchPengurang') && !e.target.closest('#pengurangDropdown')) {
            const dropdown = document.getElementById('pengurangDropdown');
            if (dropdown) dropdown.style.display = 'none';
        }
    });

    // Add item to table
    addToTableBtn.addEventListener('click', function() {
        if (!selectedPenambah) {
            alert('Please select a Penambah code first');
            return;
        }

        const pengurangCode = searchPengurangInput.value;
        const pengurangName = searchPengurangInput.getAttribute('data-name') || '';
        const quantity = parseInt(quantityInput.value) || 0;

        if (!pengurangCode) {
            alert('Please select a Pengurang code');
            return;
        }

        if (quantity <= 0) {
            alert('Please enter a valid quantity');
            return;
        }

        // Check if this pengurang is already in the table
        const existingItemIndex = orderItems.findIndex(item => item.pengurang === pengurangCode);

        if (existingItemIndex >= 0) {
            // Update quantity if already exists
            orderItems[existingItemIndex].quantity += quantity;
        } else {
            // Add new item
            orderItems.push({
                pengurang: pengurangCode,
                pengurangName: pengurangName,
                quantity: quantity
            });
        }

        // Refresh table
        renderOrderItems();

        // Clear pengurang input and quantity
        searchPengurangInput.value = '';
        searchPengurangInput.removeAttribute('data-name');
        searchPengurangInput.removeAttribute('data-length');
        quantityInput.value = '1';
    });

    // Render order items table
    function renderOrderItems() {
        if (orderItems.length === 0) {
            orderItemsTable.innerHTML = `
                <tr id="emptyTableRow">
                    <td colspan="4" class="text-center text-muted">No items added yet</td>
                </tr>
            `;
            return;
        }

        let html = '';
        orderItems.forEach((item, index) => {
            html += `
                <tr>
                    <td>${item.pengurang}</td>
                    <td>${item.pengurangName}</td>
                    <td>${item.quantity}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        orderItemsTable.innerHTML = html;

        // Add remove event listeners
        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                orderItems.splice(index, 1);
                renderOrderItems();
            });
        });
    }

    // Process order button
    processOrderBtn.addEventListener('click', function() {
        if (!selectedPenambah) {
            alert('Please select a Penambah code first');
            return;
        }

        if (orderItems.length === 0) {
            alert('Please add at least one item to the order');
            return;
        }

        if (confirm('Are you sure you want to process these orders?')) {
            // Clear existing form
            const pengurangContainer = document.getElementById('pengurangContainer');
            const quantityContainer = document.getElementById('quantityContainer');
            pengurangContainer.innerHTML = '';
            quantityContainer.innerHTML = '';

            // Set penambah value
            document.getElementById('formPenambah').value = selectedPenambah.kode;

            // const currentFrequency = parseInt(frequencyInput.value);
            // document.getElementById('formFrequency').value = currentFrequency;

            const currentFrequency = document.getElementById('frequencyInput').value;
            document.getElementById('formFrequency').value = currentFrequency;

            // Add all items to the form with proper array notation
            orderItems.forEach((item, index) => {
                // Create hidden inputs for pengurang array
                const pengurangInput = document.createElement('input');
                pengurangInput.type = 'hidden';
                pengurangInput.name = 'pengurang[]';  // Array notation
                pengurangInput.value = item.pengurang;
                pengurangContainer.appendChild(pengurangInput);

                // Create hidden inputs for quantity array
                const quantityInput = document.createElement('input');
                quantityInput.type = 'hidden';
                quantityInput.name = 'quantity[]';  // Array notation
                quantityInput.value = item.quantity;
                quantityContainer.appendChild(quantityInput);
            });

            // Submit the form
            document.getElementById('orderSubmitForm').submit();
        }
    });

    // Initialize
    renderOrderItems();
});
</script>
@endsection
