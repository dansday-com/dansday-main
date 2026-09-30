<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SkillController extends Controller
{
    public function index()
    {
        $design_skills = DB::table('skill')
            ->where('type', '=', 'design')
            ->orderBy('order', 'asc')
            ->get() ;
        $dev_skills = DB::table('skill')
            ->where('type', '=', 'development')
            ->orderBy('order', 'asc')
            ->get() ;
        $user = User::find(1);
        return view('admin.pages.skills.skills')
            ->with('design_skills', $design_skills)
            ->with('dev_skills', $dev_skills)
            ->with('user', $user);
    }

    public function store(Request $request)
    {
        $data = array(
            "type"=>$request->input("type"),
            "title"=>$request->input("title"),
            "order_design"=>$request->input("order_design"),
            "order_dev"=>$request->input("order_dev"),
        );
        $order = ($data["type"] == 'design') ? $data["order_design"] : $data["order_dev"];

        if(!empty($data)){
            $validate = Validator::make($data, [
                "title" => ['required', 'string', 'max:55'],
            ]);
            if($validate->fails()){
                return redirect('/admin/skills')
                    -> with('error-validation', '')
                    -> with('error-modal', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                $skill = new Skill();
                $skill->type = $data["type"];
                $skill->title = $data["title"];
                $skill->order = $order;
                $skill->save();
                return redirect('/admin/skills') -> with('ok-add', '');
            }
        } else {
            return redirect('/admin/skills') -> with('error-validation', '');
        }
    }

    public function show($id)
    {
        $skill = Skill::find($id);
        $user = User::find(1);
        if($skill != null){
            return view('admin.pages.skills.single')
                ->with('skill', $skill)
                ->with('user', $user);
        } else {
            return redirect('/admin/skills');
        }
    }

    public function update($id, Request $request)
    {
        $data = array(
            "type"=>$request->input("type"),
            "title"=>$request->input("title"),
            "order"=>$request->input("order"),
        );
        if(!empty($data)){
            $validate = Validator::make($data, [
                "title" => ['required', 'string', 'max:55'],
            ]);
            if($validate->fails()){
                return redirect('/admin/skills/'.$id)
                    -> with('error-validation', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                $data_new = array(
                    "type"=>$data['type'],
                    "title"=>$data['title'],
                    "order"=>$data['order'],
                );
                Skill::where("id", $id)->update($data_new);
                return redirect('/admin/skills') -> with('ok-update', '');
            }
        } else {
            return redirect('/admin/skills') -> with('error-validation', '');
        }
    }

    public function orderUp($id)
    {
        $skill_1 = Skill::where("id", $id)->get();
        $skill_2 = DB::table('skill')
            ->where('order', '=', $skill_1[0]['order']-1)
            ->where('type', '=', $skill_1[0]['type'])
            ->get() ;
        $data_new_1 = array(
            "order"=>$skill_2[0]->order,
        );
        Skill::where("id", $skill_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$skill_1[0]['order'],
        );
        Skill::where("id", $skill_2[0]->id)->update($data_new_2);
        return redirect('/admin/skills') -> with('ok-update', '');
    }

    public function orderDown($id)
    {
        $skill_1 = Skill::where("id", $id)->get();
        $skill_2 = DB::table('skill')
            ->where('order', '=', $skill_1[0]['order']+1)
            ->where('type', '=', $skill_1[0]['type'])
            ->get() ;
        $data_new_1 = array(
            "order"=>$skill_2[0]->order,
        );
        Skill::where("id", $skill_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$skill_1[0]['order'],
        );
        Skill::where("id", $skill_2[0]->id)->update($data_new_2);
        return redirect('/admin/skills') -> with('ok-update', '');
    }

    public function destroy($id, Skill $skill)
    {
        $validate = Skill::where("id", $id)->get();
        if(!empty($validate)){
            $type = $validate[0]['type'];
            Skill::where("id", $validate[0]['id'])->delete();
            $skills = DB::table('skill')
                ->where('type', '=', $type)
                ->orderBy('order', 'asc')
                ->get() ;
            $i = 1;
            foreach ($skills as $skill):
                $data_new = array(
                    "order"=>$i,
                );
                Skill::where("id", $skill->id)->update($data_new);
                $i++;
            endforeach;
            return redirect('/admin/skills') -> with('ok-delete', '');
        } else {
            return redirect('/admin/skills') -> with('no-delete', '');
        }
    }
}
