<!DOCTYPE html>
<html>
<head>
    <title>BiggerHat Automated Character Card Art</title>
    <style>
        @page { margin: 1px; }
        body { margin: 1px; }
        html { margin: 1px; }
    </style>
</head>
<body>
@foreach($images as $image)
    @if ($image['type'] == \App\Enums\PDFImageTypeEnum::Double)
        <img src='data:image/jpg;base64,{{ $image["url"] }}' style="vertical-align: top; width:390px; margin-left: 1px; margin-right:1px; margin-bottom: 2px;"  alt="{{ $image['name'] }}" />
    @else
        <img src='data:image/jpg;base64,{{ $image["url"] }}' style="vertical-align: top; width:195px; margin-left: 1px; margin-right:1px; margin-bottom: 2px;"  alt="{{ $image['name'] }}" />
    @endif
@endforeach
</body>
</html>
