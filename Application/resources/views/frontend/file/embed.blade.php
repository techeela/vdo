<!DOCTYPE html> <html lang="{{ getLang() }}"> <head> 
    <meta charset="UTF-8"> <meta http-equiv="X-UA-Compatible" content="IE=edge"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>{{ pageTitle($__env) }}</title> <link rel="icon" href="{{ asset($settings['website_favicon']) }}" /> 
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap/bootstrap.min.css') }}"> 
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/plyr/plyr.min.css') }}" /> 
    <link rel="stylesheet" href="{{ asset(mix('assets/css/application.css')) }}"> </head> 
    <body class="pt-0"> <div class="fileviewer-embed">
        <video controls playsinline id="video"></video>
        </div> 
        </body> 
        <script src="{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}"></script> 
        <script src="{{ asset('assets/vendor/libs/bootstrap/bootstrap.bundle.min.js') }}"></script> 
        

        <video id="player" playsinline controls data-poster="/path/to/poster.jpg">
  <source src="<?php echo $fileEntry->cdn_url?>"  />


  <!-- Captions are optional -->
  <track kind="captions" label="English captions" src="/path/to/captions.vtt" srclang="en" default />
</video>


        <script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
        </html>