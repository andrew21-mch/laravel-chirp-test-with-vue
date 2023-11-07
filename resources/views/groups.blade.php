<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Groups</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<style>
    tbody, thead, tr, td {
        color: white;
    }
</style>
<body>

    <div class="container mt-5 bg-dark text-white p-5">
        <h1 class="text-center">Groups</h1>

        <div class="row justify-content-center mt-4 text-white">
            <div class="col-lg-8">
                <table class="table ">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Password</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>Name 1</td>
                            <td>Email 1</td>
                            <td>Password 1</td>
                        </tr>

                        <tr>
                            <td>Name 2</td>
                            <td>Email 2</td>
                            <td>Password 2</td>
                        </tr>
                    </tbody>
                </table>

                <div class="text-center">
                    <a href="{{ route('groups.create') }}" class="btn btn-primary">Add User</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
