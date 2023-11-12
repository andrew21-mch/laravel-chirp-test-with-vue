@component('mail::message')
# {{ $subject }}

Dear {{ $userName }},

We're excited to inform you that a new image has been uploaded to our system.

**Image Description:** {{ $image->description }}

To view more details and explore the image, click the link below:

@component('mail::button', ['url' => route('galery.show', ['id' => $image->id])])
View More
@endcomponent

Thank you for being a part of our community!

Best regards,
Your App Team
@endcomponent
