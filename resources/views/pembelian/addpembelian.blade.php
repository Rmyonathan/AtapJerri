@extends('layout.Nav')

@section('content')
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-shopping-cart mr-2"></i>Transaksi Pembelian</h2>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Data Transaksi</h5>
        </div>
        <div class="card-body">
            <form id="transactionForm">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="no_nota">No. Nota</label>
                            <input type="text" class="form-control" id="no_nota" name="nota" value="{{ $nota ?? 'BL/04/25-00006' }}" readonly style="background-color: #ffc107; color: #000; font-weight: bold;">
                        </div>

                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ date('Y-m-d') }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier">Supplier</label>
                            <input type="text" id="supplier" name="supplier_display" class="form-control" placeholder="Masukkan kode atau nama supplier">
                            <input type="hidden" id="kode_supplier" name="kode_supplier">
                            <div id="supplierDropdown" class="dropdown-menu" style="display: none; position: absolute; width: 100%;"></div>
                        </div>
                    </div>

                    <div class="col-md-6">


                        <div class="form-group">
                            <label for="metode_pembayaran">Metode Pembayaran</label>
                            <select class="form-control" id="metode_pembayaran" name="metode_pembayaran">
                                <option selected disabled value=""> Pilih Metode Pembayaran</option>
                                <option value="Tunai">Tunai</option>
                                <option value="Non Tunai">Non Tunai</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cara_bayar">Cara Bayar</label>
                            <select class="form-control" id="cara_bayar" name="cara_bayar">
                                <option value="">Pilih Metode Dulu</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Rincian Barang</h5>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addItemModal">
                <i class="fas fa-plus-circle"></i> Tambah Barang
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Keterangan</th>
                            <th>Harga</th>
                            <th>Qty</th>
                            <th>Panjang</th>
                            <th>Total</th>
                            <th>Diskon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemsList">
                        <!-- Dynamic items will be added here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Total</label>
                        <input type="text" class="form-control text-right" id="total" name="total" readonly value="0">
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="discount_checkbox">
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">Disc(%)</span>
                            </div>
                            <input type="number" class="form-control" id="discount_percent" value="0" disabled>
                            <input type="text" class="form-control text-right" id="discount_amount" value="0" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="ppn_checkbox">
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">PPN</span>
                            </div>
                            <input type="text" class="form-control text-right" id="ppn_amount" value="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Cara Bayar</label>
                        <select class="form-control" id="cara_bayar_akhir">
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit">Debit</option>
                            <option value="Cicilan">Cicilan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" class="form-control text-right" id="grand_total" readonly value="0" style="font-size: 18px; font-weight: bold;">
                    </div>
                    <div class="form-group text-right mt-4">
                        <button type="button" class="btn btn-success" id="saveTransaction">
                            <i class="fas fa-save"></i> Simpan Transaksi
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelTransaction">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="addSupplierForm" action="{{ route('suppliers.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel">Tambah Supplier Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama">Nama</label>
                        <input type="text" class="form-control" id="nama" name="nama" required>
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <input type="text" class="form-control" id="alamat" name="alamat" required>
                    </div>
                    <div class="form-group">
                        <label for="pemilik">Pemilik</label>
                        <input type="text" class="form-control" id="pemilik" name="pemilik" required>
                    </div>
                    <div class="form-group">
                        <label for="telepon_fax">Telepon/Fax</label>
                        <input type="text" class="form-control" id="telepon_fax" name="telepon_fax" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                    </div>
                    <div class="form-group">
                        <label for="hp_contact_person">HP Contact Person</label>
                        <input type="text" class="form-control" id="hp_contact_person" name="hp_contact_person" required>
                    </div>
                    <div class="form-group">
                        <label for="kode_kategori">Kode Kategori</label>
                        <input type="text" class="form-control" id="kode_kategori" name="kode_kategori" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Tambah Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addItemForm">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Updated kode_barang input group with dropdown and search button -->
                            <div class="form-group position-relative">
                                <label for="kode_barang">Kode Barang</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="kode_barang" name="kode_barang" placeholder="Masukkan kode barang" autocomplete="off" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="findItem" data-toggle="modal" data-target="#kodeBarangSearchModal">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Autocomplete dropdown -->
                                <div class="dropdown-menu" id="kodeBarangDropdown" style="display: none; max-height: 280px; overflow-y: auto; width: 100%;"></div>
                            </div>

                            <div class="form-group">
                                <label for="nama_barang">Nama Barang</label>
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                            </div>

                            <div class="form-group">
                                <label for="keterangan">Keterangan</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="harga">Harga</label>
                                <input type="number" class="form-control" id="harga" name="harga" required>
                            </div>

                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                            </div>

                            <div class="form-group">
                                <label for="panjang">Panjang</label>
                                <input type="number" class="form-control" id="panjang" name="panjang" value="0" min="0" step="0.01">
                            </div>

                            <div class="form-group">
                                <label for="diskon">Diskon (%)</label>
                                <input type="number" class="form-control" id="diskon" name="diskon" value="0" min="0" max="100">
                            </div>

                            <div class="form-group">
                                <label for="satuan">Satuan</label>
                                <select class="form-control" id="satuan" name="satuan">
                                    <option value="PCS">PCS</option>
                                    <option value="MTR">MTR</option>
                                    <option value="BTG">BTG</option>
                                    <option value="LBR">LBR</option>
                                    <option value="UNIT">UNIT</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Kode</th>
                                            <th>Keterangan</th>
                                            <th>Harga</th>
                                            <th>Qty</th>
                                            <th>Panjang</th>
                                            <th>Total</th>
                                            <th>Satuan</th>
                                            <th>Disc(%)</th>
                                            <th>Sub Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemPreview">
                                        <!-- Item preview will be shown here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="saveItemBtn">Tambahkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Invoice -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Invoice Pembelian</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Invoice Content -->
                <div id="invoiceContent">
                    <h4>No Nota: <span id="invoiceNota"></span></h4>
                    <p>Tanggal: <span id="invoiceTanggal"></span></p>
                    <p>Supplier: <span id="invoiceSupplier"></span></p>
                    <p>Grand Total: <span id="invoiceGrandTotal"></span></p>
                    <!-- Tambahkan detail lainnya jika diperlukan -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="printInvoiceBtn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-secondary" id="backToFormBtn">
                    <i class="fas fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for searching Kode Barang -->
<div class="modal fade" id="kodeBarangSearchModal" tabindex="-1" role="dialog" aria-labelledby="kodeBarangSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kodeBarangSearchModalLabel">Cari Kode Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="searchKodeBarangInput" placeholder="Masukkan kode atau nama barang">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="searchKodeBarangBtn">
                            <i class="fas fa-search"></i> Cari
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Panjang</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="kodeBarangSearchResults">
                            <!-- Search results will be added here by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
{{-- IMPORTANT: Define global variables here that will be used in the external JS file --}}
<script>
    // Expose Laravel routes as global window variables
    window.supplierSearchUrl = "{{ route('api.suppliers.search') }}";
    window.storeTransactionUrl = "{{ route('pembelian.store') }}";
    window.printInvoiceUrl = "{{ url('pembelian/lihatnota') }}/";
    window.backToPembelian = "{{ route('pembelian.index') }}"
    window.kodeBarangSearchUrl = "{{ route('kodeBarang.search') }}";
    window.getPanelInfoUrl = "{{ route('panel.by.kodeBarang') }}";

    window.csrfToken = "{{ csrf_token() }}";
</script>

{{-- Include the external JS file using file_get_contents to load directly from views directory --}}
<script>

$('#metode_pembayaran').on('change', function () {
        const metode = $(this).val();
        $('#cara_bayar').html('<option value="">Loading...</option>');

        $.ajax({
            url: '{{ url("api/cara-bayar/by-metode") }}',
            method: 'GET',
            data: { metode: metode },
            success: function (data) {
                let options = '<option value="">-- Pilih Cara Bayar --</option>';
                data.forEach(cb => {
                    options += `<option value="${cb.nama}">${cb.nama}</option>`;
                });
                $('#cara_bayar').html(options);
            },
            error: function () {
                $('#cara_bayar').html('<option value="">Gagal load data</option>');
            }
        });
    });

    $('#cara_bayar').on('change', function () {
        const selected = $(this).val();
        $('#cara_bayar_akhir')
            .html(`<option value="${selected}">${selected}</option>`)
            .val(selected);
    });
{!! file_get_contents(resource_path('views/scripts/pembelian.js')) !!}
</script>
@endsection
