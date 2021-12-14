<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Trail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class TrailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $trail = Trail::with('location','location.county')->where('id','>=',1);
        $userTrail=Favorite::select('trail_id')->where('user_id','=',$request->uuid)->get();
        // 篩選欄位條件
        if (isset($request->filters)) {
            if($request->input('filters.altitude1') == $request->input('filters.altitude2')){
                $altitude1 = null;
                $altitude2 = $request->input('filters.altitude2');
            } else{
                $altitude1 = $request->input('filters.altitude1');
                $altitude2 = $request->input('filters.altitude2');
            }
            foreach ($request->filters as $key => $filter) {
                //迴圈取得所有filter參數
                switch ($key) {
                    case 'title':
                        $filter?$trail->where($key, 'like', "%$filter%"):'';
                        break;
                    case 'difficulty':
                        $filter?$trail->where('difficulty',$filter):'';
                        break;
                    case 'evaluation':
                        $filter?$trail->where('evaluation',$filter):'';
                        break;
                    case 'classification':
                        $filter?$trail->where('classification_id',$filter):'';
                        break;
                    case 'altitude1':
                        $altitude1?$trail->where('altitude','<=',$altitude1):'';
                        break;
                    case 'altitude2':
                        $altitude2?$trail->where('altitude','>=',$altitude2):'';
                        break;
                    case 'county':
                        $filter?$trail->whereHas('location.county',function($q) use($filter){
                            $q->where('id',$filter);
                        }):'';
                        break;
                    case 'collection':
                        $filter?$trail->whereHas('collections',function($q) use($filter){
                            $q->where('collection_id',$filter);
                        }):'';
                        break;
                    default:
                        break;
                }
            }
        }
        $result=$trail->get();
        for($i=0;$i<count($result);$i++)
        {
            $result[$i]["favorite"]=false;
            for($j=0;$j<count($userTrail);$j++)
            {
                if($result[$i]->id===$userTrail[$j]->trail_id)
                {
                    $result[$i]["favorite"]=true;
                }
            }
        }
        return $result;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //$result = trail::with('location','location.county','users')->find($id); //查詢id動作
        $result=trail::with('location','location.county','users:id,name')->find($id);
        return $result;
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
        //
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
}
