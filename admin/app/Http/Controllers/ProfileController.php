<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index()
    {
        $user = User::find(1);
        return view('admin.pages.profile')
            ->with('user', $user);
    }

    public function update(Request $request, User $user)
    {
        $user = User::find(1);
        $data = array(
            "name"=>$request->input("name"),
            "email"=>$request->input("email"),
            "image_profile"=>$request->file("image_profile"),
            "image_profile_current"=>$request->input("image_profile_current"),
            "pass_current"=>$request->input("pass_current"),
            "pass_new_1"=>$request->input("pass_new_1"),
            "pass_new_2"=>$request->input("pass_new_2"),
        );
        $route_image = $data['image_profile_current'];

        if(!empty($data)){
            $validate = Validator::make($data, [
                'name' => ['string', 'max:55'],
                'email' => ['email:rfc,dns', 'max:55'],
            ]);
            if($validate->fails()){
                return redirect('/admin/profile')
                    -> with('error-validation', '')
                    -> withErrors($validate)
                    -> withInput();
            }else{
                if (!empty($data["image_profile"])){
                    $validateImage = Validator::make($data, [
                        'image_profile' => ['file', 'mimes:jpg,jpeg,png'],
                    ]);
                    if ($validateImage->fails()) {
                        return redirect('/admin/profile')
                            ->with('error-validation', '')
                            ->withErrors($validateImage)
                            ->withInput();
                    }
                    $disk = Storage::disk('uploads');
                    if ($route_image != '' && uploads_path_safe_to_delete($route_image)) {
                        $p = uploads_path_for_disk($route_image);
                        if ($p !== '' && $disk->exists($p)) {
                            $disk->delete($p);
                        }
                    }
                    $route_image = 'uploads/' . $data["image_profile"]->storeAs('admin/profile', 'profile_image_' . mt_rand(100, 999) . '.' . $data["image_profile"]->guessExtension(), 'uploads');
                }
                if ($data["pass_current"] != '' || $data["pass_new_1"] != '' || $data["pass_new_2"] != ''){
                    $validate = Validator::make($data, [
                        'pass_current' => ['required'],
                        'pass_new_1' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[@$!%*#?&-_]/'],
                        'pass_new_2' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[@$!%*#?&-_]/'],
                    ]);
                    if($validate->fails()){
                        return redirect('/admin/profile')
                            -> with('error-validation', '')
                            -> withErrors($validate)
                            -> withInput();
                    } else {
                        $password_current = $data["pass_current"];
                        if (Hash::check($password_current, $user->password)) {
                            if($data["pass_new_1"] == $data["pass_new_2"]){
                                $data_new = array(
                                    "name"=>$data['name'],
                                    "email"=>$data['email'],
                                    "image"=>$route_image,
                                    "password"=>Hash::make($data["pass_new_1"]),
                                );
                            } else {
                                return redirect('/admin/profile')
                                    -> with('error-validation-new-pass', '')
                                    -> with('error-validation', '');
                            }
                        }else{
                            return redirect('/admin/profile')
                                -> with('error-validation-pass-current', '')
                                -> with('error-validation', '');
                        }
                    }
                } else {
                    $data_new = array(
                        "name"=>$data['name'],
                        "email"=>$data['email'],
                        "image"=>$route_image,
                    );
                }
                User::where("id", '1')->update($data_new);
                return redirect('/admin/profile') -> with('ok-update', '');
            }
        } else {
            return redirect('/admin/profile') -> with('error-validation', '');
        }
    }
}
