<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voter Cards Report</title>
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
        .constituency-section {
            margin-top: 15px;
            padding: 10px 0;
            border-top: 1px solid #dee2e6;
        }
        .constituency-label {
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
            color: #212529;
        }
        .constituency-name {
            font-size: 14px;
            font-weight: bold;
            color: #212529;
            margin: 5px 0;
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
        }
        th { 
            background-color: #343a40; 
            color: white; 
            padding: 8px; 
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            border: 1px solid #dee2e6;
        }
        td { 
            padding: 6px; 
            text-align: center; 
            border: 1px solid #dee2e6;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
        }
        .summary {
            margin-top: 15px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
        .summary p {
            margin: 3px 0;
            font-size: 10px;
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
        
        <p class="report-title">RPT020: Election Projections</p>
        <p class="report-generated">Report Generated: {{ date('n/j/Y g:i:s A') }}</p>
        
        <!-- Constituency Section -->
        <div class="constituency-section">
            @if($constituency_id || $constituency_name)
                <p class="constituency-label">CONSTITUENCY</p>
                @if($constituency_id)
                    <p class="constituency-name">{{ $constituency_id }}</p>
                @elseif($constituency_name)
                    <p class="constituency-name">{{ strtoupper($constituency_name) }}</p>
                @endif
            @else
                <p class="constituency-label">ALL CONSTITUENCIES</p>
            @endif
        </div>
    </div>

    <!-- Additional Search Parameters -->
    @if($polling)
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
                    <th>{{ strtoupper(str_replace('_', ' ', $column)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($results as $row)
                <tr>
                    
                    @foreach($columns as $column)
                        <td>
                            @php
                                $columnKey = strtolower(str_replace(' ', '_', $column));
                                
                                
                                // Map column names to actual database fields
                                $columnMapping = [
                                    // COI/DNA mapping
                                    'coi_count' => 'dna_count',
                                    'coi_%' => 'dna_percentage',
                                    'coi_percentage' => 'dna_percentage',
                                    
                                    // Percentage mappings (from "fnm %" to "fnm_percentage")
                                    'fnm_%' => 'fnm_percentage',
                                    'plp_%' => 'plp_percentage',
                                    'dna_%' => 'dna_percentage',
                                    'other_%' => 'other_percentage',
                                    'totals' => 'total_party_count',
                                    'no_vote_%' => 'no_vote_percentage',
                                ];
                                
                                // Use mapped column if exists
                                if (isset($columnMapping[$columnKey])) {
                                    $columnKey = $columnMapping[$columnKey];
                                }
                                
                                // Handle different column formats
                                if (str_contains($columnKey, 'percentage')) {
                                    echo ($row->$columnKey ?? '0.00') . '%';
                                } else {
                                    echo $row->$columnKey ?? '-';
                                }
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
