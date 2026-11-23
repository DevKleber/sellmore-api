<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class Arquivo extends Model
{
    private const LADO_DA_MINIATURA = 200;

    public static function saveFile($file, $folder)
    {
        return self::send($file, $folder);
    }

    public static function send($file, $folder)
    {
        if (null == $folder) {
            $folder = 'default';
        }

        return self::crop($file, $folder);
    }

    public static function formataIsImage($request)
    {
        $type = current(explode('/', $request->getMimeType()));
        if ('image' != $type) {
            return false;
        }

        return true;
    }

    private static function crop($file, $path)
    {
        $type = current(explode('/', $file->getMimeType()));
        if ('image' != $type) {
            return false;
        }

        $arquivo = $file->getClientOriginalName();
        $filename = Str::random(10).date('dmYHis').auth('api')->user()->id;
        $extension = pathinfo($arquivo, PATHINFO_EXTENSION);

        $name = storage_path().'/app/public/'.$path.'/'.$filename.'_min.'.$extension;

        if (Image::decode($file)->cover(self::LADO_DA_MINIATURA, self::LADO_DA_MINIATURA)->save($name)) {
            $caminho = 'public/storage/'.$path.'/'.$filename.'_min.'.$extension;
            if ($caminho) {
                return $caminho;
            }
        }

        return false;
    }
}
