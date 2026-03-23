<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = DB::table('testimonial')
            ->orderBy('order', 'asc')
            ->get() ;
        $user = User::find(1);
        return view('admin.pages.testimonials.testimonials')
            ->with('testimonials', $testimonials)
            ->with('user', $user);
    }

    public function store(Request $request)
    {
        $data = array(
            "name"=>$request->input("name"),
            "company"=>$request->input("company"),
            "description"=>$request->input("description"),
            "order"=>$request->input("order"),
        );

        if(!empty($data)){
            $validate = Validator::make($data, [
                "name" => ['required', 'string', 'max:55'],
                "company" => ['required', 'string', 'max:55'],
                "description" => ['required', 'string', 'max:255'],
            ]);
            if($validate->fails()){
                return redirect('/admin/testimonials')
                    -> with('error-validation', '')
                    -> with('error-modal', '')
                    -> withErrors($validate)
                    -> withInput();
            }
            $testimonial = new Testimonial();
            $testimonial->name = $data["name"];
            $testimonial->company = $data["company"];
            $testimonial->description = $data['description'];
            $testimonial->order = $data['order'];
            $testimonial->save();
            return redirect('/admin/testimonials') -> with('ok-add', '');
        }
        return redirect('/admin/testimonials') -> with('error-validation', '');
    }

    public function show($id)
    {
        $testimonial = Testimonial::find($id);
        $user = User::find(1);
        if($testimonial != null){
            return view('admin.pages.testimonials.single')
                ->with('testimonial', $testimonial)
                ->with('user', $user);
        } else {
            return redirect('/admin/testimonials');
        }
    }

    public function update($id, Request $request)
    {
        $data = array(
            "name"=>$request->input("name"),
            "company"=>$request->input("company"),
            "description"=>$request->input("description"),
            "order"=>$request->input("order"),
        );

        if(!empty($data)){
            $validate = Validator::make($data, [
                "name" => ['required', 'string', 'max:55'],
                "company" => ['required', 'string', 'max:55'],
                "description" => ['required', 'string', 'max:255'],
            ]);
            if($validate->fails()){
                return redirect('/admin/testimonials/'.$id)
                    -> with('error-validation', '')
                    -> withErrors($validate)
                    -> withInput();
            }
            $data_new = array(
                "name"=>$data['name'],
                "company"=>$data['company'],
                "description"=>$data['description'],
                "order"=>$data['order'],
            );
            Testimonial::where("id", $id)->update($data_new);
            return redirect('/admin/testimonials') -> with('ok-update', '');
        }
        return redirect('/admin/testimonials') -> with('error-validation', '');
    }

    public function orderUp($id)
    {
        $testimonial_1 = Testimonial::where("id", $id)->get();
        $testimonial_2 = DB::table('testimonial')
            ->where('order', '=', $testimonial_1[0]['order']-1)
            ->get() ;
        $data_new_1 = array(
            "order"=>$testimonial_2[0]->order,
        );
        Testimonial::where("id", $testimonial_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$testimonial_1[0]['order'],
        );
        Testimonial::where("id", $testimonial_2[0]->id)->update($data_new_2);
        return redirect('/admin/testimonials') -> with('ok-update', '');
    }

    public function orderDown($id)
    {
        $testimonial_1 = Testimonial::where("id", $id)->get();
        $testimonial_2 = DB::table('testimonial')
            ->where('order', '=', $testimonial_1[0]['order']+1)
            ->get() ;
        $data_new_1 = array(
            "order"=>$testimonial_2[0]->order,
        );
        Testimonial::where("id", $testimonial_1[0]['id'])->update($data_new_1);
        $data_new_2 = array(
            "order"=>$testimonial_1[0]['order'],
        );
        Testimonial::where("id", $testimonial_2[0]->id)->update($data_new_2);
        return redirect('/admin/testimonials') -> with('ok-update', '');
    }

   public function destroy($id, Testimonial $testimonial)
   {
       $validate = Testimonial::where("id", $id)->get();
       if(!empty($validate)){
            Testimonial::where("id", $validate[0]['id'])->delete();
            $testimonials = DB::table('testimonial')
               ->orderBy('order', 'asc')
               ->get() ;
            $i = 1;
            foreach ($testimonials as $testimonial):
               $data_new = array(
                   "order"=>$i,
               );
               Testimonial::where("id", $testimonial->id)->update($data_new);
               $i++;
            endforeach;
            return redirect('/admin/testimonials') -> with('ok-delete', '');
       } else {
            return redirect('/admin/testimonials') -> with('no-delete', '');
       }
   }

}
