<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bonanza Loot Deck — Print</title>
    <style>
        /* Cut-grid of the printer-friendly card images: 3 across x 2 down per
           Letter page, safe margins, faint guide around each card for cutting. */
        @page { margin: 0.3in; }
        * { box-sizing: border-box; }
        body { margin: 0; }

        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        .cell {
            display: inline-block;
            vertical-align: top;
            padding: 0.03in;
            border: 1px dashed #ccc;
            margin: 0 0.02in 0.04in 0.02in;
        }
        .cell img {
            display: block;
            width: 2.5in;
            height: 4.318in; /* preserves the 2.75:4.75 card aspect */
        }
    </style>
</head>
<body>
@foreach(array_chunk($images, 6) as $page)
    <div class="page">
        @foreach($page as $image)
            <span class="cell">
                <img src="data:image/png;base64,{{ $image['image'] }}" alt="{{ $image['name'] }}" />
            </span>
        @endforeach
    </div>
@endforeach
</body>
</html>
