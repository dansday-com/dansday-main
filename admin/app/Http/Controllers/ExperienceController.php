<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExperienceController extends Controller
{
    public function index()
    {
        $edu_experiences = DB::table('experience')
            ->where('type', '=', 'education')
            ->orderBy('order', 'asc')
            ->get() ;
        $emp_experiences = DB::table('experience')
            ->where('type', '=', 'employment')
            ->orderBy('order', 'asc')
            ->get() ;
        $user = User::find(1);
        return view('admin.pages.experiences.experiences')
            ->with('edu_experiences', $edu_experiences)
            ->with('emp_experiences', $emp_experiences)
            ->with('user', $user);
    }

    public function store(Request $request)
    {
        $data = array(
            "type"=>$request->input("type"),
            "title"=>$request->input("title"),
            "period"=>$request->input("period"),
            "description"=>$request->input("description"),
            "order_edu"=>$request->input("order_edu"),
            "order_emp"=>$request->input("order_emp"),
        );
        $order = ($data["type"] == 'education') ? $data["order_edu"] : $data["order_emp"];

        if(!empty($data)){
            $validate = Validator::make($data, [
                "title" => ['required', 'string', 'max:55'],
                "period" => ['required', 'string', 'max:55'],
                "description" => ['required', 'string', 'max:255'],
            ]);
            if($validate->fails()){
                return redirect('/admin/experiences')
                    -> with('error-validation', '')
                    -> with('error-modal', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                $experience = new Experience();
                $experience->type = $data["type"];
                $experience->title = $data["title"];
                $experience->period = $data["period"];
                $experience->description = $data["description"];
                $experience->order = $order;
                $experience->save();
                return redirect('/admin/experiences') -> with('ok-add', '');
            }
        } else {
            return redirect('/admin/experiences') -> with('error-validation', '');
        }
    }

    public function show($id)
    {
        $experience = Experience::find($id);
        $user = User::find(1);
        if($experience != null){
            return view('admin.pages.experiences.single')
                ->with('experience', $experience)
                ->with('user', $user);
        } else {
            return redirect('/admin/experiences');
        }
    }

    public function update($id, Request $request)
    {
        $data = array(
            "type"=>$request->input("type"),
            "title"=>$request->input("title"),
            "period"=>$request->input("period"),
            "description"=>$request->input("description"),
            "order"=>$request->input("order"),
        );

        if(!empty($data)){
            $validate = Validator::make($data, [
                "title" => ['required', 'string', 'max:55'],
                "period" => ['required', 'string', 'max:55'],
                "description" => ['required', 'string', 'max:255'],
            ]);
            if($validate->fails()){
                return redirect('/admin/experiences/'.$id)
                    -> with('error-validation', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                $data_new = array(
                    "type"=>$data['type'],
                    "period"=>$data['period'],
                    "title"=>$data['title'],
                    "description"=>$data['description'],
                    "order"=>$data['order'],
                );
                Experience::where("id", $id)->update($data_new);
                return redirect('/admin/experiences') -> with('ok-update', '');
            }
        } else {
            return redirect('/admin/experiences') -> with('error-validation', '');
        }
    }

    public function orderUp($id)
    {
        $exp_1 = Experience::where("id", $id)->get();
        $exp_2 = DB::table('experience')
            ->where('order', '=', $exp_1[0]['order']-1)
            ->where('type', '=', $exp_1[0]['type'])
            ->get() ;
        $data_new_1 = array(
            "order"=>$exp_2[0]->order,
        );
        Experience::where("id", $exp_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$exp_1[0]['order'],
        );
        Experience::where("id", $exp_2[0]->id)->update($data_new_2);
        return redirect('/admin/experiences') -> with('ok-update', '');
    }

    public function orderDown($id)
    {
        $exp_1 = Experience::where("id", $id)->get();
        $exp_2 = DB::table('experience')
            ->where('order', '=', $exp_1[0]['order']+1)
            ->where('type', '=', $exp_1[0]['type'])
            ->get() ;
        $data_new_1 = array(
            "order"=>$exp_2[0]->order,
        );
        Experience::where("id", $exp_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$exp_1[0]['order'],
        );
        Experience::where("id", $exp_2[0]->id)->update($data_new_2);
        return redirect('/admin/experiences') -> with('ok-update', '');
    }

    public function destroy($id, Experience $experience)
    {
        $validate = Experience::where("id", $id)->get();
        if(!empty($validate)){
            $type = $validate[0]['type'];
            Experience::where("id", $validate[0]['id'])->delete();
            $experiences = DB::table('experience')
                ->where('type', '=', $type)
                ->orderBy('order', 'asc')
                ->get() ;
            $i = 1;
            foreach ($experiences as $experience):
                $data_new = array(
                    "order"=>$i,
                );
                Experience::where("id", $experience->id)->update($data_new);
                $i++;
            endforeach;
            return redirect('/admin/experiences') -> with('ok-delete', '');
        } else {
            return redirect('/admin/experiences') -> with('no-delete', '');
        }
    }
}
