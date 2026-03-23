<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public function index()
    {
        $user = User::find(1);
        $categories = Category::all();
        $articles = DB::table('articles')->orderBy('created_at', 'desc')->get();
        return view('admin.pages.articles.posts')
            ->with('articles', $articles)
            ->with('categories', $categories)
            ->with('user', $user);
    }

    public function create()
    {
        $user = User::find(1);
        $categories = Category::all();
        $articles = Article::all();
        return view('admin.pages.articles.post')
            ->with('categories', $categories)
            ->with('articles', $articles)
            ->with('user', $user);
    }

    public function store(Request $request)
    {
        $data = [
            'enable'      => $request->input('enable'),
            'title'       => $request->input('title'),
            'short_desc'  => $request->input('short_desc'),
            'author'      => $request->input('author'),
            'category'    => $request->input('category'),
            'description' => $request->input('description'),
            'image'       => $request->file('image'),
        ];

        $validate = Validator::make($data, [
            'title'       => ['required', 'string', 'max:55'],
            'short_desc'  => ['nullable', 'string', 'max:255'],
            'author'      => ['string', 'max:55'],
            'category'    => ['string', 'max:55'],
            'description' => ['required'],
            'image'       => ['required', 'file', 'mimes:jpg,jpeg,png'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/articles/post')
                ->with('error-validation', '')
                ->withErrors($validate)
                ->withInput();
        }

        $disk = Storage::disk('uploads');
        $route_image = $data['image']->storeAs('img/articles', 'post_image_' . mt_rand(10, 9999) . '.' . $data['image']->guessExtension(), 'uploads');

        $tempFiles = $disk->files('img/temp');
        foreach ($tempFiles as $tempPath) {
            $name = basename($tempPath);
            $destPath = 'img/articles/' . $name;
            if ($disk->exists($tempPath)) {
                $disk->put($destPath, $disk->get($tempPath));
                $disk->delete($tempPath);
            }
        }

        $post = new Article();
        $post->enable = ($data['enable'] == 'on') ? 1 : 0;
        $post->title = $data['title'];
        $post->short_desc = $data['short_desc'];
        $post->description = str_replace([$disk->url('img/temp'), 'uploads/img/temp'], [$disk->url('img/articles'), 'uploads/img/articles'], $data['description']);
        $post->image = 'uploads/' . $route_image;
        $post->author = $data['author'];
        $post->category_id = $data['category'];
        $post->save();
        return redirect('/admin/articles/posts')->with('ok-add', '');
    }

    public function show($id)
    {
        $post = Article::find($id);
        $user = User::find(1);
        $categories = Category::all();
        if ($post != null) {
            return view('admin.pages.articles.single')
                ->with('post', $post)
                ->with('categories', $categories)
                ->with('user', $user);
        }
        return redirect('/admin/articles/posts');
    }

    public function update($id, Request $request)
    {
        $data = [
            'id'            => $request->input('id'),
            'enable'        => $request->input('enable'),
            'title'         => $request->input('title'),
            'short_desc'    => $request->input('short_desc'),
            'author'        => $request->input('author'),
            'category'      => $request->input('category'),
            'description'   => $request->input('description'),
            'image'         => $request->file('image'),
            'image_current' => $request->input('image_current'),
        ];

        $validate = Validator::make($data, [
            'title'       => ['required', 'string', 'max:55'],
            'short_desc'  => ['nullable', 'string', 'max:255'],
            'author'      => ['string', 'max:55'],
            'category'    => ['string', 'max:55'],
            'description' => ['required'],
        ]);
        if ($validate->fails()) {
            return redirect('/admin/articles/post/' . $data['id'])
                ->with('error-validation', '')
                ->withErrors($validate)
                ->withInput();
        }
        if ($data['image'] != '') {
            $validate2 = Validator::make($data, ['image' => ['file', 'mimes:jpg,jpeg,png']]);
            if ($validate2->fails()) {
                return redirect('/admin/articles/post/' . $data['id'])
                    ->with('error-validation', '')
                    ->withErrors($validate2)
                    ->withInput();
            }
        }

        $disk = Storage::disk('uploads');
        $route_image = $data['image_current'];
        if ($data['image'] != '') {
            if ($data['image_current'] != '' && uploads_path_safe_to_delete($data['image_current'])) {
                $p = uploads_path_for_disk($data['image_current']);
                if ($p !== '' && $disk->exists($p)) {
                    $disk->delete($p);
                }
            }
            $route_image = 'uploads/' . $data['image']->storeAs('img/articles', 'post_image_' . mt_rand(10, 9999) . '.' . $data['image']->guessExtension(), 'uploads');
        } elseif ($data['image_current'] == '' || $data['image_current'] === null) {
            $post = Article::find($id);
            if ($post && $post->image != '' && uploads_path_safe_to_delete($post->image)) {
                $p = uploads_path_for_disk($post->image);
                if ($p !== '' && $disk->exists($p)) {
                    $disk->delete($p);
                }
            }
            $route_image = '';
        }

        $tempFiles = $disk->files('img/temp');
        foreach ($tempFiles as $tempPath) {
            $name = basename($tempPath);
            $destPath = 'img/articles/' . $name;
            if ($disk->exists($tempPath)) {
                $disk->put($destPath, $disk->get($tempPath));
                $disk->delete($tempPath);
            }
        }

        Article::where('id', $id)->update([
            'enable'      => ($data['enable'] == 'on') ? 1 : 0,
            'title'       => $data['title'],
            'short_desc'  => $data['short_desc'],
            'description' => str_replace([$disk->url('img/temp'), 'uploads/img/temp'], [$disk->url('img/articles'), 'uploads/img/articles'], $data['description']),
            'image'       => $route_image,
            'author'      => $data['author'],
            'category_id' => $data['category'],
        ]);
        return redirect('/admin/articles/posts')->with('ok-update', '');
    }

    public function destroy($id)
    {
        $article = Article::where('id', $id)->first();
        if ($article) {
            $disk = Storage::disk('uploads');
            if (!empty($article->image) && uploads_path_safe_to_delete($article->image)) {
                $p = uploads_path_for_disk($article->image);
                if ($p !== '' && $disk->exists($p)) {
                    $disk->delete($p);
                }
            }
            Article::where('id', $id)->delete();
            return redirect('/admin/articles/posts')->with('ok-delete', '');
        }
        return redirect('/admin/articles/posts')->with('no-delete', '');
    }
}
