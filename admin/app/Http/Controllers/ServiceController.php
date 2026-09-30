<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    public function index()
    {
        $services = DB::table('service')
            ->orderBy('order', 'asc')
            ->get() ;
        $user = User::find(1);
        return view('admin.pages.services.services')
            ->with('services', $services)
            ->with('user', $user);
    }

    public function store(Request $request)
    {
        $data = array(
            "title"=>$request->input("title"),
            "description"=>$request->input("description"),
            "order"=>$request->input("order"),
            "info" =>$request->input("info"),
        );

        if(!empty($data)){
            $validate = Validator::make($data, [
                "title" => ['required', 'string', 'max:55'],
                "description" => ['required', 'string', 'max:255'],
                "info" => ['required', 'string', 'max:510'],
            ]);
            if($validate->fails()){
                return redirect('/admin/services')
                    -> with('error-validation', '')
                    -> with('error-modal', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                $service = new Service();
                $service->title = $data["title"];
                $service->description = $data['description'];
                $service->order = $data['order'];
                $service->info = $data['info'];
                $service->save();
                return redirect('/admin/services') -> with('ok-add', '');
            }
        } else {
            return redirect('/admin/services') -> with('error-validation', '');
        }
    }

    public function show($id)
    {
        $service = Service::find($id);
        $user = User::find(1);
        if($service != null){
            return view('admin.pages.services.single')
                ->with('service', $service)
                ->with('user', $user);
        } else {
            return redirect('/admin/services');
        }
    }

    public function update($id, Request $request)
    {
        $data = array(
            "title"=>$request->input("title"),
            "description"=>$request->input("description"),
            "order"=>$request->input("order"),
            "info" =>$request->input("info"),
        );

        if(!empty($data)){
            $validate = Validator::make($data, [
                "title" => ['required', 'string', 'max:55'],
                "description" => ['required', 'string', 'max:255'],
                "info" => ['required', 'string', 'max:510'],
            ]);
            if($validate->fails()){
                return redirect('admin/services/'.$id)
                    -> with('error-validation', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                $data_new = array(
                    "title"=>$data['title'],
                    "description"=>$data['description'],
                    "order"=>$data['order'],
                    "info"=>$data['info'],
                );
                Service::where("id", $id)->update($data_new);
                return redirect('/admin/services') -> with('ok-update', '');
            }
        } else {
            return redirect('/admin/services') -> with('error-validation', '');
        }
    }

    public function orderUp($id)
    {
        $service_1 = Service::where("id", $id)->get();
        $service_2 = DB::table('service')
            ->where('order', '=', $service_1[0]['order']-1)
            ->get() ;
        $data_new_1 = array(
            "order"=>$service_2[0]->order,
        );
        Service::where("id", $service_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$service_1[0]['order'],
        );
        Service::where("id", $service_2[0]->id)->update($data_new_2);
        return redirect('/admin/services') -> with('ok-update', '');
    }

    public function orderDown($id)
    {
        $service_1 = Service::where("id", $id)->get();
        $service_2 = DB::table('service')
            ->where('order', '=', $service_1[0]['order']+1)
            ->get() ;
        $data_new_1 = array(
            "order"=>$service_2[0]->order,
        );
        Service::where("id", $service_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$service_1[0]['order'],
        );
        Service::where("id", $service_2[0]->id)->update($data_new_2);
        return redirect('/admin/services') -> with('ok-update', '');
    }

   public function destroy($id, Service $service)
   {
       $validate = Service::where("id", $id)->get();
       if(!empty($validate)){
            Service::where("id", $validate[0]['id'])->delete();
            $services = DB::table('service')
               ->orderBy('order', 'asc')
               ->get() ;
            $i = 1;
            foreach ($services as $service):
               $data_new = array(
                   "order"=>$i,
               );
               Service::where("id", $service->id)->update($data_new);
               $i++;
            endforeach;
            return redirect('/admin/services') -> with('ok-delete', '');
       } else {
            return redirect('/admin/services') -> with('no-delete', '');
       }
   }
}
