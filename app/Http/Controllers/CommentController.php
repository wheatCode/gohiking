<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Comment;
use App\Models\CommentsImage;
use App\Models\UserLikeComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use DateTime;
use Hamcrest\Arrays\IsArray;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rule(), $this->errorMassage());
        if ($validator->fails()) {
            $error = "";
            $errors = $validator->errors();
            foreach ($errors->all() as $message)
                $error .= $message . "\n";
            return ['status' => 0, 'massage' => $error];
        }
        //新增一筆 並取得新增的comment_id
        $last_comments_id = $this->newCommentAndSelectLastId($request)->id;//Model->id
        $filePaths=[];
        //評論圖片如果有上傳，才執行
        if ($request->hasFile('images') && isset($request->tag_id)) {
            $images = $request->file('images');
            //適配單張及多張上傳
            if(is_Array($images)){
                foreach($images as $key=>$value){
                    $path=$this->upload_s3($images[$key]);
                    array_push($filePaths,$path);
                }
            }else{
                $path=$this->upload_s3($images);
                array_push($filePaths,$path);
            }
            $tags=$request->tag_id;
            //適配單筆及多筆新增
            if(is_Array($tags)){
                if(count($filePaths)==count($tags)){
                    foreach($tags as $k=>$v){
                        $this->newCommentImage($request,$tags[$k],$filePaths[$k],$last_comments_id);
                    }
                }else{
                    return 'images和tags不一樣長';
                }
            }else{
                $this->newCommentImage($request,$tags,$filePaths,$last_comments_id);
            }
        }

        return Comment::with('commentsImages.tag')->where('trail_id', '=', $request->trail_id)->find($last_comments_id);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $stars=$this->getTrailStars($id,$request);
        //取的目前的comment data
        $comments = Comment::with('user:id,name', 'commentsImages:id,comment_id,s3_filePath,tag_id')
            ->where('trail_id', '=', $id)
            ->withCount([
                'userLikeComment as like' => function (Builder $likequery) {
                    $likequery->where('status', 1); //1 = like count 算出status等於1的有多少
                }, 'userLikeComment as dislike' => function (Builder $dislikequery) {
                    $dislikequery->where('status', -1); //-1 = dislike count 算出status等於-1的有多少
                }
            ])
            ->get();
        $userlikestatus = UserLikeComment::select('comment_id', 'status')->where('user_id', '=', $request->uuid)->get();
        //這是爛code，我盡力了
        //取得使用者對評論喜歡狀態
        foreach ($comments as $key => $values) {
            $comments[$key]['likestatus'] = false;
            $comments[$key]['dislikestatus'] = false;
            foreach ($userlikestatus as $k => $v) {
                if ($userlikestatus[$k]->comment_id == $comments[$key]->id) {
                    switch ($userlikestatus[$k]->status) {
                        case 1:
                            $comments[$key]['likestatus'] = true;
                            $comments[$key]['dislikestatus'] = false;
                            break;
                        case -1:
                            $comments[$key]['likestatus'] = false;
                            $comments[$key]['dislikestatus'] = true;
                            break;
                    }
                }
            }
        }
        //取得圖片URL
        foreach ($comments as $imgkey => $values) {
            foreach ($values->commentsImages as $value) {
                $value['s3_url'] = $this->getFileUrl_s3($value->s3_filePath);
            }
        }

        return response()->json(array(
            'totalPeople' => $stars['totalPeople'], //評論總人數
            'avgStar' => $stars['avgStar'], //平均星數
            'stars' => $stars['starsgroup'], //各星級人數
            'comments' => $comments, //評論內容
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    private function rule()
    {
        return
            [
                'user_id' => 'bail|required',
                'trail_id' => 'bail|required',
                'date' => 'bail|required|date:' . date("Y-m-d"),
                'star' => 'bail|required',
                'difficulty' => 'bail|required',
                'beauty' => 'bail|required',
                'duration' => 'bail|required',
                'content' => 'bail|required',
                'images'=>'bail|max:15',
                'images.*'=>'bail|image|mimes:jpeg,jpg,png,gif',
                'tag_id'=>'bail|max:15'
            ];
    }

    private function errorMassage()
    {
        return
            [
                'user_id.required' => '使用者_id必填',
                'trail_id.required' => '步道_id必填',
                'date.required' => '日期必填',
                'star.required' => '星級必填',
                'difficulty.required' => '難易度必填',
                'beauty.required' => '景色必填',
                'duration.required' => '耗時必填',
                'content.required' => '評論必填',
            ];
    }

    private function upload_s3($uploadImage)
    {
        $date = new DateTime();
        $timestamp =  $date->getTimestamp();
        $filePath = 'imgs/' . $timestamp . '.jpg';
        if (gettype($uploadImage) == 'object') {
            $filePath = 'imgs1/' . $timestamp . '.jpg';
            return Storage::disk('s3')->putFileAs('imgs1', $uploadImage, $timestamp . '.jpg') ? $filePath : false;
        } else {
            list($baseType, $image) = explode(';', $uploadImage);
            list(, $image) = explode(',', $image);
            $image = base64_decode($image);
            return Storage::disk('s3')->put('imgs/' . $timestamp . '.jpg', $image) ? $filePath : false;
        }
    }

    private function getFileUrl_s3($fileName)
    {
        $client = Storage::disk('s3')->getDriver()->getAdapter()->getClient();
        $command = $client->getCommand('GetObject', [
            'Bucket' => 'monosparta-test',
            'Key' => $fileName
        ]);
        $request = $client->createPresignedRequest($command, '+7 days');
        $presignedUrl = (string)$request->getUri();
        return $presignedUrl;
    }

    private function newCommentAndSelectLastId($request)
    {
        //新增評論
        $comments = new Comment([
            'user_id'=>$request->user_id,
            'trail_id'=>$request->trail_id,
            'date'=>$request->date,
            'star'=>$request->star,
            'difficulty'=>$request->difficulty,
            'beauty'=>$request->beauty,
            'duration'=>$request->duration,
            'content'=>$request->content,
        ]); 
        $comments->save(); //新增
        return Comment::select('id')->where('user_id', '=', $request->user_id)->latest('id')->first();
    }

    private function newCommentImage($request,$tag,$path,$last_comments_id)
    {
        $commentsImages = new CommentsImage([
            'comment_id'=>$last_comments_id,
            'user_id'=>$request->user_id,
            's3_filePath'=>$path,
            'tag_id'=>$tag,
        ]);
        $commentsImages->save();
    }

    private function getTrailStars($trail_id,$request)
    {
        $totalPeople = count(Comment::select('star')->where('trail_id', '=', $trail_id)->get());
        $avgStar = Comment::select('star')->where('trail_id', '=', $trail_id)->avg('star');
        $stars = Comment::select('star', DB::raw('count(*) as count'))
            ->where('trail_id', '=', $trail_id)
            ->groupBy('star')
            ->get();
        //starts 取的資料是統計資料庫的 count數字1~5 不方便前端存取
        //startsgroup 回傳 前端方便存取的格式
        $starsgroup = [
            "one" => "0",
            "two" => "0",
            "three" => "0",
            "four" => "0",
            "five" => "0"
        ];
        //判斷 $stars的星級是哪一個，再塞入 starsgroup $stars人數
        foreach ($stars as $key => $value) {
            switch ($value->star) {
                case 1:
                    $starsgroup['one'] = $value->count;
                    break;
                case 2:
                    $starsgroup['two'] = $value->count;
                    break;
                case 3:
                    $starsgroup['three'] = $value->count;
                    break;
                case 4:
                    $starsgroup['four'] = $value->count;
                    break;
                case 5:
                    $starsgroup['five'] = $value->count;
                    break;
            }
        }

        return ['totalPeople'=>$totalPeople,'avgStar'=>$avgStar,'starsgroup'=>$starsgroup];
    }
}
