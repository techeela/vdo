<?php

curl --request POST \
     --url https://video.bunnycdn.com/library/135513/videos/fetch \
     --header 'AccessKey: 028b3698-5642-4b07-b8742b003b90-01b9-43c1' \
     --header 'accept: application/json' \
     --header 'content-type: application/*+json' \
     --data '
{
  "url": "string",
  "headers": {
    "additionalProp": "string"
  }
}
'
