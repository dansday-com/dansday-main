@php $dir = 'uploads/img/general/favicon'; @endphp
<link rel="icon" type="image/png" href="{{ upload_url("$dir/favicon-96x96.png") }}" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="{{ upload_url("$dir/favicon.svg") }}" />
<link rel="shortcut icon" href="{{ upload_url("$dir/favicon.ico") }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ upload_url("$dir/apple-touch-icon.png") }}" />
<link rel="manifest" href="{{ upload_url("$dir/site.webmanifest") }}" />
