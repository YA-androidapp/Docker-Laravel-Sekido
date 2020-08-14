<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function covers($path)
    {
        Log::debug(get_class($this).' '.__FUNCTION__.'()');
        Log::debug('User: '.Auth::user());
        Log::debug('$path: '.$path);

        $path = str_replace('/', DIRECTORY_SEPARATOR, Storage::path('covers').'/'.basename($path));

        if('local'===config('filesystems.default', 'local')){
            $is_valid = (file_exists($path) && $img_type = exif_imagetype($path));
        } else if('s3'===config('filesystems.default', 'local')){
            $tempurl = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(1));
            $is_valid = ($img_type = exif_imagetype($tempurl));
        }

        if($is_valid){
            $contents = Storage::get($path);
            // Log::debug('$contents: '.$contents);
            $response = Response::make($contents, 200);
            $response->header('Content-Type', 'image/jpeg');
            return $response;
        }else{
            abort(404);
        }
    }

    public function documents($path)
    {
        Log::debug(get_class($this).' '.__FUNCTION__.'()');
        Log::debug('User: '.Auth::user());
        Log::debug('$path: '.$path);

        $path = str_replace('/', DIRECTORY_SEPARATOR, Storage::path('documents').'/'.basename($path));
        Log::debug('$path: '.$path);

        $magic = file_get_contents($path, false, null, 0, 12);

        if(file_exists($path) && (strpos($magic, "%PDF-1") === 0)){
            $contents = Storage::get($path);
            // Log::debug('$contents: '.$contents);
            $response = Response::make($contents, 200);
            $response->header('Content-Type', 'application/pdf');
            return $response;
        }else if(file_exists($path) && $img_type = exif_imagetype($path)){
            $contents = Storage::get($path);
            // Log::debug('$contents: '.$contents);
            $response = Response::make($contents, 200);
            $response->header('Content-Type', image_type_to_mime_type($img_type));
            return $response;
        }else{
            abort(404);
        }
    }

    public function musics($path)
    {
        Log::debug(get_class($this).' '.__FUNCTION__.'()');
        Log::debug('User: '.Auth::user());
        Log::debug('$path: '.$path);

        $path = str_replace('/', DIRECTORY_SEPARATOR, Storage::path('musics').'/'.basename($path));
        Log::debug('$path: '.$path);

        $getID3 = new \getID3();

        if('local'===config('filesystems.default', 'local')){
            $tag = $getID3->analyze($path);
            Log::debug('$tag: '.print_r($tag, true));//[fileformat]

            if(file_exists($path) && ('mp3' === $tag['fileformat'])){
                $contents = Storage::get($path);
                // Log::debug('$contents: '.$contents);
                $response = Response::make($contents, 200);
                $response->header('Content-Type', 'audio/mpeg');
                return $response;
            }else{
                abort(404);
            }
        } else if('s3'===config('filesystems.default', 'local')){
            $targetFile = uniqid();
            $inputStream = Storage::disk('s3')->getDriver()->readStream($path);
            Log::debug('$targetFile: '.$targetFile);
            Log::debug('$inputStream: '.$inputStream);
            Storage::disk('local')->getDriver()->writeStream($targetFile, $inputStream);

            if(Storage::disk('local')->exists($targetFile)){
                $targetFilecontents = Storage::disk('local')->get($targetFile);
                // Log::debug('$targetFilecontents: '.$targetFilecontents);
                $tag = $getID3->analyze(storage_path('app').'/'.$targetFile);
                // Log::debug('$tag: '.print_r($tag, true));//[fileformat]

                Storage::disk('local')->delete($targetFile);

                if('mp3' === $tag['fileformat']){
                    $contents = Storage::get($path);
                    // Log::debug('$contents: '.$contents);
                    $response = Response::make($contents, 200);
                    $response->header('Content-Type', 'audio/mpeg');
                    return $response;
                }else{
                    abort(404);
                }
            }else{
                abort(404);
            }
        }
    }
}
