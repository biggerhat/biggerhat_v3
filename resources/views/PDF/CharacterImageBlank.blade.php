<!DOCTYPE html>
<html>
<head>
    <title>BiggerHat Automated Character Card Art</title>
    <style>
        @page { margin: 5px; }
        body { margin: 5px; }
        html { margin: 1px; }
    </style>
</head>
<body>
@foreach($images as $image)
    @if ($image['type'] == \App\Enums\PDFImageTypeEnum::Double)
        <img src='data:image/jpg;base64,{{ $image["url"] }}' style="vertical-align: top; width:382px; margin-left: 1px; margin-right:1px; margin-bottom: 2px;"  alt="{{ $image['name'] }}" />
    @else
        <img src='data:image/jpg;base64,{{ $image["url"] }}' style="vertical-align: top; width:187px; margin-left: 1px; margin-right:1px; margin-bottom: 2px;"  alt="{{ $image['name'] }}" />
    @endif
@endforeach
</body>
</html>
