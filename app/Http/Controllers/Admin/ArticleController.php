<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Article\Store;
use App\Models\Article;
use App\Models\ArticleTag;
use App\Models\Category;
use App\Models\Config;
use App\Models\Tag;
use Baijunyao\LaravelUpload\Upload;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request, Article $articleModel)
    {
        $wd = $request->input('wd', '');

        if (empty($wd)) {
            $id = [];
        } else {
            $id = $articleModel->searchArticleGetId($wd);
        }

        $article = Article::with('category')
            ->orderBy('created_at', 'desc')
            ->when($wd, function ($query) use ($id) {
                return $query->whereIn('id', $id);
            })
            ->withTrashed()
            ->paginate(15);
        $assign =compact('article');

        return view('admin.article.index', $assign);
    }

    public function create()
    {
        $category = Category::all();
        $tag      = Tag::all();
        $author   = Config::where('name', 'AUTHOR')->value('value');
        $assign   = compact('category', 'tag', 'author');

        return view('admin.article.create', $assign);
    }

    public function uploadImage()
    {
        $result = Upload::image('editormd-image-file', 'uploads/article');
        if ($result['status_code'] === 200) {
            $data = [
                'success' => 1,
                'message' => $result['message'],
                'url'     => $result['data'][0]['path'],
            ];
        } else {
            $data = [
                'success' => 0,
                'message' => $result['message'],
                'url'     => '',
            ];
        }

        return response()->json($data);
    }

    public function store(Store $request)
    {
        $data = $request->except('_token');

        if ($request->hasFile('cover')) {
            $result = Upload::file('cover', 'uploads/article');
            if ($result['status_code'] === 200) {
                $data['cover'] = $result['data'][0]['path'];
            }
        }

        $tag_ids = $data['tag_ids'];
        unset($data['tag_ids']);
        $article = Article::create($data);

        $articleTag = new ArticleTag();
        $articleTag->addTagIds($article->id, $tag_ids);

        return redirect('admin/article/index');
    }

    public function edit($id)
    {
        $category = Category::all();
        $tag      = Tag::all();
        $article  = Article::withTrashed()->find($id);
        $article->setAttribute('tag_ids', ArticleTag::where('article_id', $id)->pluck('tag_id')->toArray());

        $assign = compact('article', 'category', 'tag');

        return view('admin.article.edit', $assign);
    }

    public function update(Store $request, ArticleTag $articleTagModel, $id)
    {
        $data = $request->except('_token');

        // 上传封面图
        if ($request->hasFile('cover')) {
            $result = Upload::file('cover', 'uploads/article');
            if ($result['status_code'] === 200) {
                $data['cover'] = $result['data'][0]['path'];
            }
        }

        $tag_ids = $data['tag_ids'];
        unset($data['tag_ids']);
        $result = Article::withTrashed()->find($id)->update($data);

        if ($result) {
            ArticleTag::where('article_id', $id)->forceDelete();
            $articleTagModel->addTagIds((int) $id, $tag_ids);
        }

        return redirect()->back();
    }

    public function destroy($id)
    {
        Article::destroy($id);

        return redirect()->back();
    }

    public function restore($id)
    {
        Article::onlyTrashed()->find($id)->restore();

        return redirect()->back();
    }

    public function forceDelete($id)
    {
        Article::onlyTrashed()->find($id)->forceDelete();

        return redirect()->back();
    }

    public function replaceView()
    {
        return view('admin.article.replaceView');
    }

    public function replace(Request $request)
    {
        $search  = $request->input('search');
        $replace = $request->input('replace');
        $data    = Article::select('id', 'title', 'keywords', 'description', 'markdown', 'html')
            ->where('title', 'like', "%$search%")
            ->orWhere('keywords', 'like', "%$search%")
            ->orWhere('description', 'like', "%$search%")
            ->orWhere('markdown', 'like', "%$search%")
            ->orWhere('html', 'like', "%$search%")
            ->get();
        foreach ($data as $k => $v) {
            Article::find($v->id)->update([
                'title'       => str_replace($search, $replace, $v->title),
                'keywords'    => str_replace($search, $replace, $v->keywords),
                'description' => str_replace($search, $replace, $v->description),
                'markdown'    => str_replace($search, $replace, $v->markdown),
                'html'        => str_replace($search, $replace, $v->html),
            ]);
        }

        return redirect()->back();
    }
}
