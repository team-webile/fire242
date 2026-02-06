<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Polling Report</title>
    <style>
        body { 
            font-family: DejaVu Sans, sans-serif; 
            font-size: 10px;
            margin: 20px;
        }
        .header-container {
            margin-bottom: 20px;
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
        }
        .header-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .header-left {
            display: table-cell;
            width: 70%;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: top;
        }
        .election-title {
            color: #dc3545;
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .report-title {
            color: #495057;
            font-size: 20px;
            font-weight: bold;
            margin: 5px 0;
        }
        .report-generated {
            color: #6c757d;
            font-size: 10px;
            margin: 3px 0;
        }
        .search-params {
            background-color: #f8f9fa;
            padding: 8px 12px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
        }
        .search-params p {
            margin: 3px 0;
            font-size: 10px;
            color: #495057;
        }
        .search-params strong {
            color: #212529;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            table-layout: fixed;
        }
        th { 
            background-color: #343a40; 
            color: white; 
            padding: 8px 4px; 
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            border: 1px solid #dee2e6;
            word-wrap: break-word;
        }
        td { 
            padding: 6px 4px; 
            text-align: center; 
            border: 1px solid #dee2e6;
            font-size: 9px;
            word-wrap: break-word;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .totals-row {
            background-color: #e9ecef !important;
            font-weight: bold;
        }
        .totals-row td {
            background-color: #e9ecef !important;
        }
        tbody tr {
            page-break-inside: avoid;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-container">
        <div class="header-row">
            <div class="header-left">
                <p class="election-title">Election 2025</p>
            </div>
            <div class="header-right">
                <!-- Logo space if needed -->
            </div>
        </div>
        
        <p class="report-title">Polling Division Report</p>
        <p class="report-generated">Report Generated: {{ date('n/j/Y g:i:s A') }}</p>
        
        @if(isset($constituency_id) && $constituency_id)
        <div style="margin-top: 10px;">
            <p style="font-size: 12px; font-weight: bold; color: #212529;">Constituency ID: {{ $constituency_id }}</p>
        </div>
        @endif
        
        @if(isset($constituency_name) && $constituency_name)
        <div style="margin-top: 5px;">
            <p style="font-size: 12px; font-weight: bold; color: #212529;">Constituency: {{ strtoupper($constituency_name) }}</p>
        </div>
        @endif
    </div>

    <!-- Additional Search Parameters -->
    @if(isset($polling) && $polling)
    <div class="search-params">
        <strong>Additional Filters:</strong>
        <p><strong>Polling Station:</strong> {{ $polling }}</p>
    </div>
    @endif

    <!-- Data Table -->
    <table>
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ strtoupper($column) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($results as $row)
                <tr class="{{ (isset($row['polling_division']) && $row['polling_division'] === 'TOTALS') ? 'totals-row' : '' }}">
                    @foreach($columns as $column)
                        <td>
                            @php
                                $col = strtolower(trim($column));
                                $value = '';
                                
                                switch ($col) {
                                    case 'polling division':
                                    case 'polling':
                                        $value = $row['polling_division'] ?? '';
                                        break;
                                    case 'constituency id':
                                        $value = $row['constituency_id'] ?? '';
                                        break;
                                    case 'constituency name':
                                        $value = $row['constituency_name'] ?? '';
                                        break;
                                    case 'total voters':
                                        $value = isset($row['total_voters']) ? (int)$row['total_voters'] : 0;
                                        break;
                                    case 'surveyed voters':
                                        $value = isset($row['surveyed_voters']) ? (int)$row['surveyed_voters'] : 0;
                                        break;
                                    case 'not surveyed':
                                        $value = isset($row['not_surveyed_voters']) ? (int)$row['not_surveyed_voters'] : 0;
                                        break;
                                    case 'surveyed %':
                                        $pct = $row['surveyed_percentage'] ?? 0;
                                        $value = is_numeric($pct) ? number_format($pct, 2) . '%' : $pct;
                                        break;
                                    case 'male %':
                                        // For totals row, leave gender percentages blank
                                        if (isset($row['polling_division']) && $row['polling_division'] === 'TOTALS') {
                                            $value = '';
                                        } elseif (isset($row['gender']['male']['percentage'])) {
                                            $pctVal = $row['gender']['male']['percentage'];
                                            $value = is_numeric($pctVal) ? number_format($pctVal, 2) . '%' : '0.00%';
                                        } else {
                                            $value = '0.00%';
                                        }
                                        break;
                                    case 'female %':
                                        // For totals row, leave gender percentages blank
                                        if (isset($row['polling_division']) && $row['polling_division'] === 'TOTALS') {
                                            $value = '';
                                        } elseif (isset($row['gender']['female']['percentage'])) {
                                            $pctVal = $row['gender']['female']['percentage'];
                                            $value = is_numeric($pctVal) ? number_format($pctVal, 2) . '%' : '0.00%';
                                        } else {
                                            $value = '0.00%';
                                        }
                                        break;
                                    case 'unspecified %':
                                        // For totals row, leave gender percentages blank
                                        if (isset($row['polling_division']) && $row['polling_division'] === 'TOTALS') {
                                            $value = '';
                                        } elseif (isset($row['gender']['unspecified']['percentage'])) {
                                            $pctVal = $row['gender']['unspecified']['percentage'];
                                            $value = is_numeric($pctVal) ? number_format($pctVal, 2) . '%' : '0.00%';
                                        } else {
                                            $value = '0.00%';
                                        }
                                        break;
                                    default:
                                        if (str_ends_with($col, ' %')) {
                                            // For totals row, leave party percentages blank
                                            if (isset($row['polling_division']) && $row['polling_division'] === 'TOTALS') {
                                                $value = '';
                                            } elseif (isset($row['parties'])) {
                                                $partyKey = trim(str_replace(' %', '', $column));
                                                $found = null;
                                                foreach ($row['parties'] as $shortName => $data) {
                                                    if (strtolower(str_replace('-', '_', $shortName)) === strtolower(str_replace('-', '_', $partyKey))) {
                                                        $found = $data;
                                                        break;
                                                    }
                                                }
                                                if ($found !== null) {
                                                    $pctVal = $found['percentage'] ?? 0;
                                                    $value = is_numeric($pctVal) ? number_format($pctVal, 2) . '%' : '0.00%';
                                                } else {
                                                    $value = '0.00%';
                                                }
                                            } else {
                                                $value = '0.00%';
                                            }
                                        } else {
                                            $value = '';
                                        }
                                }
                                
                                echo $value !== '' ? $value : '&nbsp;';
                            @endphp
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align: center; padding: 20px;">
                        No data available
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by Voter Management System | Page 1 of 1</p> 
    </div>
</body>
</html>
