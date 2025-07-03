@php
use Riskihajar\Terbilang\Facades\Terbilang;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Penjualan #{{ $transaction->no_transaksi }}</title>
    <style>
        * { box-sizing: border-box; }

        @page {
            size: 21.59cm 13.97cm;
            margin: 0mm;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 8pt;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        .page {
            width: 95%;
            padding: 8mm 5mm 5mm 5mm;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .header { text-align: center; line-height: 1.1; }
        .header strong { font-size: 10pt; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2px;
        }

        .info-left {
            display: flex;
            flex-direction: column;
        }

        .info-right {
            text-align: flex;
            align-self: flex-start;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            table-layout: fixed;
        }
        .item-table th, .item-table td {
    border: 1px solid #000;
    padding: 6px 5px; /* perbesar jarak dalam sel */
    font-size: 8pt; /* biarkan tetap */
    vertical-align: middle;
    word-wrap: break-word;
}
        .item-table th { font-weight: bold; }

        tr.empty-row td {
            border-bottom: 1px solid #eee;
            border-top: 1px solid #eee;
            color: #fff;
        }
        tr.empty-row td:first-child { border-left: 1px solid #000; }
        tr.empty-row td:last-child { border-right: 1px solid #000; }
        tr.last-empty-row td { border-bottom: 1px solid #000; }

        .footer-container {
            width: 100%;
            margin-top: auto;
            padding-top: 5px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 5px;
        }
        .summary-table th, .summary-table td {
            border: 1px solid #000;
            padding: 2px 3px;
        }
        .summary-table th {
            text-align: left;
            font-weight: bold;
        }

        .notes-terbilang-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            padding-top: 3px;
        }

        .notes-section {
            flex-basis: 60%;
            font-size: 8pt;
            line-height: 1.1;
        }

        .terbilang-section {
            flex-basis: 40%;
            font-size: 8pt;
            font-style: italic;
            text-align: right;
            padding-left: 10px;
        }

        .signature-row {
            width: 100%;
            display: flex;
            justify-content: space-between;
            margin-top: 100px;
            font-size: 9pt;
            padding-top: 50px;
        }
        .signature-left { float: left; text-align: center; width: 20%; }
        .signature-right { float: right; text-align: center; width: 20%; }

        .right { text-align: right; }
        .center { text-align: center; }

        .page-break { page-break-after: always; }

        .attention-box {
            font-size: 8pt;
            margin-top: 10px;
        }
    </style>
</head>
<body>

@php
    $defaultCompany = \App\Models\Perusahaan::where('is_default', true)->first() ?? new \App\Models\Perusahaan();
    $itemsPerPage = 5;
    $groupedItems = $transaction->items->chunk($itemsPerPage);
    $totalPages = $groupedItems->count();
    $pageNum = 0;
@endphp

@foreach ($groupedItems as $chunk)
    @php $pageNum++; @endphp
    <div class="page">
        <div class="header">
            <strong>{{ $defaultCompany->nama ?? 'CV. ALUMKA CIPTA PRIMA' }}</strong><br>
            {{ $defaultCompany->alamat ?? 'JL. SINAR RAGA ABI HASAN NO.1553 RT.022 RW.008' }}<br>
            {{ $defaultCompany->kota ?? '8 ILIR' }}, {{ $defaultCompany->kode_pos ?? 'ILIR TIMUR II' }}<br>
            TELP. {{ $defaultCompany->telepon ?? '(0711) 311158' }} &nbsp;&nbsp; FAX {{ $defaultCompany->fax ?? '(0711) 311158' }}<br>
            NO FAKTUR: {{ $transaction->no_transaksi }}
        </div>

       <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 10px;">
    <div>
        <div><strong>Kepada:</strong></div>
        <div>Nama: {{ $transaction->customer->nama ?? '-' }}</div>
        <div>Telp: {{ $transaction->customer->telepon ?? '-' }}</div>
        <div>Alamat: {{ $transaction->customer->alamat ?? '-' }}</div>
    </div>
    <div style="text-align: right; white-space: nowrap; font-size: 6pt;">
        <div>Tanggal: {{ \Carbon\Carbon::parse($transaction->tanggal)->format('d M Y H:i') }}</div>
        <div>Halaman: {{ $pageNum }} / {{ $totalPages }}</div>
    </div>
</div>



        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 15%;">Kode Barang</th>
                    <th style="width: 30%;">Nama Barang</th>
                    <th style="width: 7%;">Qty</th>
                    <th style="width: 15%;">Harga Satuan</th>
                    <th style="width: 8%;">Disc %</th>
                    <th style="width: 10%;">Disc Rp</th>
                    <th style="width: 15%;">Sub Total</th>
                </tr>
            </thead>
            <tbody>
                @php $rowCount = 0; @endphp
                @foreach ($chunk as $i => $item)
                    @php $rowCount++; @endphp
                    <tr>
                        <td class="center">{{ (($pageNum - 1) * $itemsPerPage) + $i + 1 }}</td>
                        <td>{{ $item->kode_barang }}</td>
                        <td>{{ $item->keterangan }}</td>
                        <td class="center">{{ $item->qty }}</td>
                        <td class="right">Rp {{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="center">{{ $item->diskon_persen ?? 0 }}</td>
                        <td class="right">Rp {{ number_format($item->diskon ?? 0, 0, ',', '.') }}</td>
                        <td class="right">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach

                @if ($loop->last)
                    @for ($j = $rowCount; $j < $itemsPerPage; $j++)
                        <tr class="empty-row {{ ($j == $itemsPerPage - 1) ? 'last-empty-row' : '' }}">
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                        </tr>
                    @endfor
                @endif
            </tbody>
        </table>

        @if ($loop->last)
        <div class="footer-container">
            <table class="summary-table">
                <tr>
                    <th style="width: 86%">TOTAL</th>
                    <td class="right" style="width: 14%">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>DISCOUNT</th>
                    <td class="right">Rp {{ number_format($transaction->discount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>PPN</th>
                    <td class="right">Rp {{ number_format($transaction->ppn, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>GRAND TOTAL</th>
                    <td class="right"><strong>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</strong></td>
                </tr>
            </table>

            <div class="attention-box">
                <strong>PERHATIAN:</strong> Barang yang sudah dibeli tidak dapat dikembalikan. Klaim hanya dilayani maksimal 3 hari setelah barang diterima.<br>
            </div>

            <div class="notes-terbilang-wrapper">
    <div class="terbilang-section">
        <strong>Terbilang:</strong> {{ ucwords(Terbilang::make($transaction->grand_total, ' rupiah')) }}<br>
        <strong>TITIPAN UANG:</strong> Rp {{ number_format($transaction->titipan_uang ?? 0, 0, ',', '.') }}<br>
        <strong>SISA PIUTANG:</strong> Rp {{ number_format(($transaction->grand_total - ($transaction->titipan_uang ?? 0)), 0, ',', '.') }}
    </div>
</div>


            <div class="signature-row">
                <div class="signature-left">
                    HORMAT KAMI<br><br><br><br><br>
                    (_____________)
                </div>
                <div class="signature-right">
                    PENERIMA<br><br><br><br><br>
                    (_____________)
                </div>
            </div>
        </div>
        @endif
    </div>

    @if (!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
