<!DOCTYPE html>
<html>
<head>
    <title>BiggerHat Automated Bonanza Deck Art</title>
    <style>
        @page { margin: 7px; }
        body { margin: 7px; }
        html { margin: 1px; }
    </style>
</head>
<body>
@foreach($images as $image)
    <img src="{{ $image["image"] }}" style="width:2.75in; height:4.75in; vertical-align: top; margin-left: 1px; margin-right:1px; margin-bottom: 2px;" alt="{{ $image["name"] }}" />
@endforeach
</body>
</html>
