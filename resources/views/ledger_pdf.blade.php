<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Buku Besar - {{ $ledgerAccount->code }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 20px;
            margin: 0 0 5px 0;
            color: #1a1a1a;
            text-transform: uppercase;
            font-weight: bold;
        }

        .header h2 {
            font-size: 14px;
            margin: 0 0 10px 0;
            color: #555;
            font-weight: normal;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .meta-info table {
            border: none;
            width: auto;
        }

        .meta-info td {
            border: none;
            padding: 3px 10px 3px 0;
        }

        .meta-info td.label {
            font-weight: bold;
            color: #555;
        }

        table.ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.ledger-table th, table.ledger-table td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }

        table.ledger-table th {
            background-color: #f5f5f5 !important;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        table.ledger-table tr.opening-row {
            background-color: #fafafa !important;
            font-style: italic;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        table.ledger-table tr.total-row {
            background-color: #f5f5f5 !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .badge-debit {
            color: #10b981;
            font-weight: bold;
        }

        .badge-credit {
            color: #f43f5e;
            font-weight: bold;
        }

        @media print {
            body {
                padding: 10px;
                font-size: 11px;
            }
            .no-print {
                display: none;
            }
            @page {
                size: A4 portrait;
                margin: 1.5cm 1cm 1.5cm 1cm;
            }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>PT Berkah Sinergi Gemilang</h1>
        <h2>Laporan Buku Besar (General Ledger)</h2>
    </div>

    <div class="meta-info">
        <table>
            <tr>
                <td class="label">Kode Akun</td>
                <td>: {{ $ledgerAccount->code }}</td>
            </tr>
            <tr>
                <td class="label">Nama Akun</td>
                <td>: {{ $ledgerAccount->name }}</td>
            </tr>
            <tr>
                <td class="label">Tipe Akun</td>
                <td>: {{ ucfirst($ledgerAccount->type) }}</td>
            </tr>
        </table>
        <table>
            <tr>
                <td class="label">Periode Laporan</td>
                <td>: {{ \Carbon\Carbon::parse($ledgerStartDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($ledgerEndDate)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Cetak</td>
                <td>: {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 12%;" class="text-center">Tanggal</th>
                <th style="width: 18%;">No. Bukti</th>
                <th>Keterangan</th>
                <th style="width: 15%;" class="text-right">Debit</th>
                <th style="width: 15%;" class="text-right">Kredit</th>
                <th style="width: 18%;" class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <!-- Saldo Awal -->
            <tr class="opening-row">
                <td class="text-center">{{ \Carbon\Carbon::parse($ledgerStartDate)->format('d/m/Y') }}</td>
                <td>-</td>
                <td>SALDO AWAL (Opening Balance)</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right" style="font-weight: bold;">
                    Rp {{ number_format($ledgerStartingBalance, 0, ',', '.') }}
                </td>
            </tr>

            <!-- Entries -->
            @foreach ($ledgerEntries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->transaction_date->format('d/m/Y') }}</td>
                    <td>{{ $entry->transaction_number }}</td>
                    <td>{{ $entry->description ?? '-' }}</td>
                    <td class="text-right">
                        @if ($entry->debit > 0)
                            <span class="badge-debit">Rp {{ number_format($entry->debit, 0, ',', '.') }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($entry->credit > 0)
                            <span class="badge-credit">Rp {{ number_format($entry->credit, 0, ',', '.') }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        Rp {{ number_format($entry->running_balance, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            <!-- Saldo Akhir -->
            <tr class="total-row">
                <td class="text-center">{{ \Carbon\Carbon::parse($ledgerEndDate)->format('d/m/Y') }}</td>
                <td>-</td>
                <td>SALDO AKHIR (Closing Balance)</td>
                <td class="text-right">
                    @if ($totalDebit > 0)
                        Rp {{ number_format($totalDebit, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">
                    @if ($totalCredit > 0)
                        Rp {{ number_format($totalCredit, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">
                    Rp {{ number_format($ledgerEndingBalance, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
