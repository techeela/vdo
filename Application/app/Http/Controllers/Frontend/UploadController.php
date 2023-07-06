<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Methods\UploadSettingsManager;
use App\Models\FileEntry;
use App\Models\StorageProvider;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Str;
use Validator;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $uploadedFile = $request->file('file');
        $uploadedFileName = $uploadedFile->getClientOriginalName();
        $validator = Validator::make($request->all(), [
            'password' => ['nullable', 'max:255'],
            'upload_auto_delete' => ['required', 'integer', 'min:0', 'max:365'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                return static::errorResponseHandler($error . ' (' . $uploadedFileName . ')');
            }
        }
        $allowedTypes = explode(',', allowedTypes());
        if (!in_array($request->type, $allowedTypes)) {
            return static::errorResponseHandler(lang('You cannot upload files of this type.', 'upload zone'));
        }
        $uploadSettings = UploadSettingsManager::handler();
        if (!$uploadSettings->active) {
            return static::errorResponseHandler(lang('Login or create account to start uploading videos', 'upload zone'));
        }
        if (!array_key_exists($request->upload_auto_delete, autoDeletePeriods())) {
            return static::errorResponseHandler(lang('Invalid file auto delete time', 'upload zone'));
        } else {
            if (autoDeletePeriods()[$request->upload_auto_delete]['days'] != 0) {
                $expiryAt = autoDeletePeriods()[$request->upload_auto_delete]['datetime'];
            } else {
                $expiryAt = null;
            }
        }
        if ($request->has('password') && !is_null($request->password) && $request->password != "undefined") {
            if ($uploadSettings->upload->password_protection) {
                $request->password = Hash::make($request->password);
            } else {
                $request->password = null;
            }
        }
        if (!is_null($uploadSettings->upload->file_size)) {
            if ($request->size > $uploadSettings->upload->file_size) {
                return static::errorResponseHandler(str_replace('{maxFileSize}', $uploadSettings->formates->file_size, lang('File is too big, Max file size {maxFileSize}', 'upload zone')));
            }
        }
        if (!is_null($uploadSettings->storage->remining->number)) {
            if ($request->size > $uploadSettings->storage->remining->number) {
                return static::errorResponseHandler(lang('insufficient storage space please ensure sufficient space', 'upload zone'));
            }
        }
        $storageProvider = StorageProvider::where([['symbol', env('FILESYSTEM_DRIVER')], ['status', 1]])->first();
        if (is_null($storageProvider)) {
            return static::errorResponseHandler(lang('Unavailable storage provider', 'upload zone'));
        }
        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));
        try {
            if ($receiver->isUploaded() === false || hasUploaded() == false) {
                return static::errorResponseHandler(str_replace('{filename}', $uploadedFileName, lang('Failed to upload ({filename})', 'upload zone')));
            }
            $save = $receiver->receive();
            if ($save->isFinished() && hasUploaded() == true) {
                $file = $save->getFile();
                $fileName = $file->getClientOriginalName();
                $fileMimeType = $file->getMimeType();
                $fileExtension = $file->getClientOriginalExtension();
                $fileSize = $file->getSize();
                if (!in_array($fileMimeType, $allowedTypes)) {
                    return static::errorResponseHandler(lang('You cannot upload files of this type.', 'upload zone'));
                }
                if ($fileSize == 0) {
                    return static::errorResponseHandler(lang('Empty files cannot be uploaded', 'upload zone'));
                }
                if (!is_null($uploadSettings->upload->file_size)) {
                    if ($fileSize > $uploadSettings->upload->file_size) {
                        return static::errorResponseHandler(str_replace('{maxFileSize}', $uploadSettings->formates->file_size, lang('File is too big, Max file size {maxFileSize}', 'upload zone')));
                    }
                }
                if (!is_null($uploadSettings->storage->remining->number)) {
                    if ($fileSize > $uploadSettings->storage->remining->number) {
                        return static::errorResponseHandler(lang('insufficient storage space please ensure sufficient space', 'upload zone'));
                    }
                }
                $ip = vIpInfo()->ip;
                $sharedId = Str::random(15);
                $userId = (Auth::user()) ? Auth::user()->id : null;
                $location = (Auth::user()) ? "users/" . hashid(userAuthInfo()->id) . "/" : "guests/";
                $handler = $storageProvider->handler;
                $uploadResponse = $handler::upload($file, $location);
                if ($uploadResponse->type == "error") {
                    return $uploadResponse;
                }
                
                $bunny_client = new \GuzzleHttp\Client();

                $url_bunny_fetch = str_replace('ottconsole.s3.ap-southeast-1.wasabisys.com','vdodelivery.b-cdn.net/ottconsole',$uploadResponse->link);

                $bunny_response = $bunny_client->request('POST', 'https://video.bunnycdn.com/library/135513/videos/fetch', [
                    'body' => '{"url":"'.$url_bunny_fetch.'"}',
                    'headers' => [
                      'AccessKey' => '028b3698-5642-4b07-b8742b003b90-01b9-43c1',
                      'accept' => 'application/json',
                      'content-type' => 'application/*+json',
                    ],
                  ]);


                $cdn_url = $bunny_response;
                //$cdn_url = "https://vz-eadc8eb2-d21.b-cdn.net/".$cdn_id."/playlist.m3u8"; 
                
                
                $createFileEntry = FileEntry::create([
                    'ip' => $ip,
                    'shared_id' => $sharedId,
                    'user_id' => $userId,
                    'storage_provider_id' => $storageProvider->id,
                    'name' => $fileName,
                    'mime' => $fileMimeType,
                    'size' => $fileSize,
                    'extension' => $fileExtension,
                    'filename' => $uploadResponse->filename,
                    'path' => $uploadResponse->path,
                    'link' => $cdn_url,
                    'password' => $request->password,
                    'expiry_at' => $expiryAt,
                ]);

                
                return response()->json([
                    'type' => 'success',
                    'file_id' => $createFileEntry->shared_id,
                    'file_link' => route('file.view', $createFileEntry->shared_id),
                ]);
            }
        } catch (Exception $e) {
            return static::errorResponseHandler(str_replace('{filename}', $uploadedFileName, lang('Failed to upload ({filename})', 'upload zone')));
        }
    }

    private static function errorResponseHandler($response)
    {
        return response()->json(['type' => 'error', 'msg' => $response]);
    }
}
