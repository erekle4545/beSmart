<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ImageManipulationResource;
use App\Models\Album;
use App\Models\ImageManipulation;
use App\Http\Requests\ImageResizeRequest;
use App\Http\Requests\UpdateImageManipulationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;


class ImageManipulationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {

        return ImageManipulationResource::collection(ImageManipulation::where('user_id',$request->user()->id)->paginate());
    }

    public function getByAlbum(Request $request,Album $album){
        if($album->user_id != $request->user()->id){
            return abort(403,'Unauthorized');
        }

        $where = [
            'album_id' => $album->id
        ];
        return ImageManipulationResource::collection(ImageManipulation::where($where)->paginate());

    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\ImageResizeRequest  $request
     * @return ImageManipulationResource
     */
    public function resize(ImageResizeRequest $request)
    {
        $all = $request->all();

        $image = $all['image'];
        unset($all['image']);
        $data = [
            'type'=>ImageManipulation::TYPE_RESIZE,
            'data'=>json_encode($all),
            'user_id'=>$request->user()->id
        ];
        if(isset($all['album_id'])){
            // TODO
            $album = Album::find($all['album_id']);
            if($album->user_id != $request->user()->id){
                return abort(403,'Unauthorized');
            }
            $data['album_id'] = $all['album_id'];
        }

        // Move image in public Where path   Str::Random()
        $wFolder  = str_replace('%','',$all['w']);
        $hFolder  = str_replace('%','',isset($all['h']));
        $whName =isset($all['h']) ? $wFolder."x".$hFolder :$wFolder."xhAuto";

        $dir = 'images/'.$whName.'/';
           $absolutePath =  public_path($dir);
            if(!File::exists($absolutePath)){
                File::makeDirectory($absolutePath,0755,true);
            }
        // END Move image in public Where path

        if($image instanceof UploadedFile){
            $data['name'] = $image->getClientOriginalName();
            $filename = pathinfo($data['name'],PATHINFO_FILENAME);
            $extension = $image->getClientOriginalExtension();
            $originalPath = $absolutePath.$data['name'];
            $image->move($absolutePath,$data['name']);
            $data['path']=$dir . $data['name']; //
        }else{
            $data['name'] = pathinfo($image,PATHINFO_BASENAME);
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $extension = pathinfo($image, PATHINFO_EXTENSION);
            $originalPath = $absolutePath. $data['name'];
            copy($image,$originalPath);
        }

        $data['path'] = $dir. $data['name'];
        $w = $all['w'];
        $h = $all['h']??false;


        // Resize

       list($width,$height,$image) =  $this->getWidthAndHeight($w,$h,$originalPath);

       $resizedFilename = $filename.'-x-'.$width.'-resized.'.$extension;
       $image->resize($width,$height)->save($absolutePath.$resizedFilename);

       $data['output_path'] = $dir.$resizedFilename;
       $imageManipulation = ImageManipulation::create($data);

//       return $imageManipulation;
        return new ImageManipulationResource($imageManipulation);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return ImageManipulationResource
     */
    public function show(Request $request,ImageManipulation $image)
    {
        if($image->user_id != $request->user()->id){
            return abort(403,'Unauthorized');
        }

        return new ImageManipulationResource($image);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateImageManipulationRequest  $request
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateImageManipulationRequest $request, ImageManipulation $imageManipulation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ImageManipulation  $image
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,ImageManipulation $image)
    {
        if($image->user_id != $request->user()->id){
            return abort(403,'Unauthorized');
        }
        $image->delete();

        return response('',204);
    }

    protected function getWidthAndHeight($w, $h, string $originalPath)
    {
       $image =  Image::make($originalPath);
       $originalWidth = $image->width();
       $originalHeight = $image->height();

       if(str_ends_with($w,'%')){
           $ratioW = str_replace('%','',$w);
           $ratioH = $h ? str_replace('%','',$h):$ratioW;

           $newWidth = $originalWidth * $ratioW / 100;
           $newHeight = $originalHeight * $ratioH / 100;

       }else{
           $newWidth = (float)$w;
           $newHeight = $h?(float)$h:($originalHeight * $newWidth/$originalWidth);
       }

       return [$newWidth,$newHeight,$image];
    }
}
