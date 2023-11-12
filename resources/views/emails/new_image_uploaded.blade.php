<!DOCTYPE html>
<html>
<head>
    <title>{{ $subject }}</title>
</head>
<body>
    <h1>{{ $subject }}</h1>

    <p>Dear {{ $user->name }},</p>

    <p>We're excited to inform you that a new image has been uploaded to our system.</p>

    <p><strong>Image Description:</strong> {{ $image->description }}</p>

    <p>To view more details and explore the image, click the link below:</p>

    <p>
        <a href="#">
            View More
        </a>
    </p>

    <p>Thank you for being a part of our community!</p>

    <p>Best regards,</p>
    <p>Your App Team</p>
</body>
</html>
