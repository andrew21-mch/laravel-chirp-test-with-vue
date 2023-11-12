<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Show Image</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4">Image Details</h1>

        <div class="card">
            <img src="{{ asset('storage/uploads/' . $image->path) }}" alt="{{ $image->name }}" class="card-img-top">
            <div class="card-body">
                <h5 class="card-title">{{ $image->name }}</h5>
                <p class="card-text">{{ $image->description }}</p>
                <a href="{{ route('uploads') }}" class="btn btn-primary">Back to Gallery</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
