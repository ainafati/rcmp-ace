<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Equipment Return Report</title>
    <style>
        /* General document styles */
        body { 
            font-family: 'Arial', sans-serif; /* Cleaner, professional font */
            font-size: 10pt; 
            color: #333;
            line-height: 1.5;
        }
        
        /* Header section with logo and title */
        .header {
            text-align: center;
            margin-bottom: 35px;
            /* Corporate Navy Blue border */
            border-bottom: 3px solid #003366; 
            padding-bottom: 15px;
        }

        .header img {
            width: 120px; 
            margin-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 20pt;
            /* Corporate Blue for the main title */
            color: #003366; 
            font-weight: 700;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 11pt;
            color: #555;
        }

        /* Data table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
        }

        th, td {
            border: 1px solid #c0c0c0; 
            padding: 12px 10px; 
            text-align: left;
            vertical-align: top;
            font-size: 10pt;
        }

thead tr {

    background-color: #343a40; 
}
th {
color: #ffffff; 
    font-weight: bold;
    font-size: 10pt;
    text-transform: uppercase;
    letter-spacing: 0.5px;
	}
        tbody tr:nth-child(even) {
            background-color: #f7f7f7; 
        }
        
        /* Style for better item detail presentation */
        .item-details strong {
            display: block;
            margin-bottom: 3px;
            color: #003366;
        }
    </style>
</head>
<body>
    
    <div class="header">
        <img src="assets/unikl-logo.png" alt="UniKL Logo">
        <h1>EQUIPMENT RETURN REPORT</h1>
        <p>Report for the period: <strong>{{start_date}}</strong> to <strong>{{end_date}}</strong></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 15%;">User Name</th>
                <th style="width: 25%;">Item Details</th>
                <th style="width: 10%;">Borrow Date</th>
                <th style="width: 10%;">Return Date</th>
                <th style="width: 10%;">Duration</th>
                <th style="width: 15%;">Return Condition</th>
                <th style="width: 10%;">Handled By</th>
            </tr>
        </thead>
        <tbody>
            {{table_rows}}
        </tbody>
    </table>

</body>
</html>