<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $user = User::find(1);
        return view('admin.pages.articles.categories')
            ->with('categories', $categories)
            ->with('user', $user);
    }

    public function store(Request $request)
    {
        $data = ['name' => $request->input('name')];

        $validate = Validator::make($data, [
            'name' => ['required', 'string', 'max:55'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/articles/categories')
                ->with('error-validation', '')
                ->with('error-modal', '')
                ->withErrors($validate)
                ->withInput();
        }

        $category = new Category();
        $category->name = $data['name'];
        $category->save();
        return redirect('/admin/articles/categories')->with('ok-add', '');
    }

    public function show($id)
    {
        $category = Category::find($id);
        $user = User::find(1);
        if ($category != null) {
            return view('admin.pages.articles.category')
                ->with('category', $category)
                ->with('user', $user);
        }
        return redirect('/admin/articles/categories');
    }

    public function update($id, Request $request)
    {
        $data = ['name' => $request->input('name')];

        $validate = Validator::make($data, [
            'name' => ['required', 'string', 'max:55'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/articles/categories')
                ->with('error-validation', '')
                ->withErrors($validate)
                ->withInput();
        }

        Category::where('id', $id)->update(['name' => $data['name']]);
        return redirect('/admin/articles/categories')->with('ok-update', '');
    }

    public function destroy($id)
    {
        $category = Category::where('id', $id)->first();
        if ($category) {
            Category::where('id', $id)->delete();
            return redirect('/admin/articles/categories')->with('ok-delete', '');
        }
        return redirect('/admin/articles/categories')->with('no-delete', '');
    }
}
