<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>File Upload</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4 text-blue">File Manager</h1>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @elseif(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif


        <form action="{{ route('uploads') }}" method="POST" enctype="multipart/form-data" class="mb-4">
            @csrf
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="file">Select file to upload:</label>
                <input type="file" class="form-control-file" id="file" name="file" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>

        <div class="row">
            @foreach ($paths as $file)
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <img src="{{ asset('storage/uploads/' . $file->path) }}" alt="Uploaded Image"
                            class="card-img-top img-fluid">

                        <div class="card-body">
                            <h5 class="card-title">{{ $file->name }}</h5>
                            <p class="card-text">

                                {{ $file->description }}

                                {{ \Illuminate\Support\Str::limit($file->description, 50, $end = '...') }}

                            </p>

                            <!-- Add the Show button using Bootstrap classes -->
                            <a href="{{ route('galery.show', ['id' => $file->id]) }}"
                                class="btn btn-outline-primary btn-sm mr-2">Show</a>

                            <!-- Add the Delete form -->
                            <form action="{{ route('delete.upload', ['filename' => $file->path]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Include Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
