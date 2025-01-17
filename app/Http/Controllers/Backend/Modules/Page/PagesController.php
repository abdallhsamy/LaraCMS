<?php

namespace App\Http\Controllers\Backend\Modules\Page;

use App\Helpers\StringHelper;
use App\Helpers\UploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\ArticleType;
use App\Models\Category;
use App\Models\Language;
use App\Models\Track;
use Illuminate\Http\Request;
use App\Models\Page;
use App\Repositories\TermRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PagesController extends Controller
{

    public $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::guard('web')->user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($isTrashed = false)
    {
        if (is_null($this->user) || !$this->user->can('page.view')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        if (request()->ajax()) {
            if ($isTrashed) {
                $pages = Page::orderBy('id', 'desc')
                    ->where('status', 0)
                    ->get();
            } else {
                $pages = Page::orderBy('id', 'desc')
                    ->where('deleted_at', null)
                    ->where('status', 1)
                    ->get();
            }

            $datatable = DataTables::of($pages, $isTrashed)
                ->addIndexColumn()
                ->addColumn(
                    'action',
                    function ($row) use ($isTrashed) {
                        $csrf = "" . csrf_field() . "";
                        $method_delete = "" . method_field("delete") . "";
                        $method_put = "" . method_field("put") . "";
                        $html = '<a class="btn waves-effect waves-light btn-info btn-sm btn-circle" title="View Page Details" href="' . route('admin.pages.show', $row->id) . '"><i class="fa fa-eye"></i></a>';

                        if ($row->deleted_at === null) {
                            $deleteRoute =  route('admin.pages.destroy', [$row->id]);
                            if ($this->user->can('page.edit')) {
                                $html .= '<a class="btn waves-effect waves-light btn-success btn-sm btn-circle ml-1 " title="Edit Page Details" href="' . route('admin.pages.edit', $row->id) . '"><i class="fa fa-edit"></i></a>';
                            }
                            if ($this->user->can('page.delete')) {
                                $html .= '<a class="btn waves-effect waves-light btn-danger btn-sm btn-circle ml-1 text-white" title="Delete Admin" id="deleteItem' . $row->id . '"><i class="fa fa-trash"></i></a>';
                            }
                        } else {
                            $deleteRoute =  route('admin.pages.trashed.destroy', [$row->id]);
                            $revertRoute = route('admin.pages.trashed.revert', [$row->id]);

                            if ($this->user->can('page.delete')) {
                                $html .= '<a class="btn waves-effect waves-light btn-warning btn-sm btn-circle ml-1" title="Revert Back" id="revertItem' . $row->id . '"><i class="fa fa-check"></i></a>';
                                $html .= '
                                <form id="revertForm' . $row->id . '" action="' . $revertRoute . '" method="post" style="display:none">' . $csrf . $method_put . '
                                    <button type="submit" class="btn waves-effect waves-light btn-rounded btn-success"><i
                                            class="fa fa-check"></i> Confirm Revert</button>
                                    <button type="button" class="btn waves-effect waves-light btn-rounded btn-secondary" data-dismiss="modal"><i
                                            class="fa fa-times"></i> Cancel</button>
                                </form>';
                                $html .= '<a class="btn waves-effect waves-light btn-danger btn-sm btn-circle ml-1 text-white" title="Delete Page Permanently" id="deleteItemPermanent' . $row->id . '"><i class="fa fa-trash"></i></a>';
                            }
                        }



                        $html .= '<script>
                            $("#deleteItem' . $row->id . '").click(function(){
                                swal.fire({ title: "Are you sure?",text: "Page will be deleted as trashed !",type: "warning",showCancelButton: true,confirmButtonColor: "#DD6B55",confirmButtonText: "Yes, delete it!"
                                }).then((result) => { if (result.value) {$("#deleteForm' . $row->id . '").submit();}})
                            });
                        </script>';

                        if ($this->user->can('page.delete')) {
                            $html .= '<script>
                            $("#deleteItemPermanent' . $row->id . '").click(function(){
                                swal.fire({ title: "Are you sure?",text: "Page will be deleted permanently, both from trash !",type: "warning",showCancelButton: true,confirmButtonColor: "#DD6B55",confirmButtonText: "Yes, delete it!"
                                }).then((result) => { if (result.value) {$("#deletePermanentForm' . $row->id . '").submit();}})
                            });
                        </script>';

                            $html .= '<script>
                            $("#revertItem' . $row->id . '").click(function(){
                                swal.fire({ title: "Are you sure?",text: "Page will be revert back from trash !",type: "warning",showCancelButton: true,confirmButtonColor: "#DD6B55",confirmButtonText: "Yes, Revert Back!"
                                }).then((result) => { if (result.value) {$("#revertForm' . $row->id . '").submit();}})
                            });
                        </script>';

                            $html .= '
                            <form id="deleteForm' . $row->id . '" action="' . $deleteRoute . '" method="post" style="display:none">' . $csrf . $method_delete . '
                                <button type="submit" class="btn waves-effect waves-light btn-rounded btn-success"><i
                                        class="fa fa-check"></i> Confirm Delete</button>
                                <button type="button" class="btn waves-effect waves-light btn-rounded btn-secondary" data-dismiss="modal"><i
                                        class="fa fa-times"></i> Cancel</button>
                            </form>';

                            $html .= '
                            <form id="deletePermanentForm' . $row->id . '" action="' . $deleteRoute . '" method="post" style="display:none">' . $csrf . $method_delete . '
                                <button type="submit" class="btn waves-effect waves-light btn-rounded btn-success"><i
                                        class="fa fa-check"></i> Confirm Permanent Delete</button>
                                <button type="button" class="btn waves-effect waves-light btn-rounded btn-secondary" data-dismiss="modal"><i
                                        class="fa fa-times"></i> Cancel</button>
                            </form>';
                        }
                        return $html;
                    }
                )

                ->editColumn('title', function ($row) {
                    return $row->title;
                })
                ->editColumn('banner_image', function ($row) {
                    if ($row->banner_image != null) {
                        return "<img src='" . asset('public/assets/images/pages/' . $row->banner_image) . "' class='img img-display-list' />";
                    }
                    return '-';
                })
                ->editColumn('image', function ($row) {
                    if ($row->image != null) {
                        return "<img src='" . asset('public/assets/images/pages/' . $row->image) . "' class='img img-display-list' />";
                    }
                    return '-';
                })
                ->addColumn('category', function ($row) {
                    $html = "";
                    $category = Category::find($row->category_id);
                    if (!is_null($category)) {
                        $html .= "<span>" . $category->name . "</span>";
                    } else {
                        $html .= "-";
                    }
                    return $html;
                })
                ->editColumn('status', function ($row) {
                    if ($row->status) {
                        return '<span class="badge badge-success font-weight-100">Active</span>';
                    } else if ($row->deleted_at != null) {
                        return '<span class="badge badge-danger">Trashed</span>';
                    } else {
                        return '<span class="badge badge-warning">Inactive</span>';
                    }
                });
            $rawColumns = ['action', 'title', 'status', 'category', 'banner_image', 'image'];
            return $datatable->rawColumns($rawColumns)
                ->make(true);
        }

        $count_pages = count(Page::select('id')->get());
        $count_active_pages = count(Page::select('id')->where('status', 1)->get());
        $count_trashed_pages = count(Page::select('id')->where('deleted_at', '!=', null)->get());
        return view('backend.pages.pages.index', compact('count_pages', 'count_active_pages', 'count_trashed_pages'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::printCategory(null, 3);
        $article_types = ArticleType::all();
        $advertisements = Advertisement::select('id', 'title', 'url')->get();
        return view('backend.pages.pages.create', compact('categories', 'article_types', 'advertisements'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('page.create')) {
            return abort(403, 'You are not allowed to access this page !');
        }

        $request->validate([
            'title'  => 'required|max:100',
            'slug'  => 'nullable|max:100|unique:pages,slug',
            'image'  => 'nullable|image',
            'banner_image'  => 'nullable|image'
        ]);

        try {
            DB::beginTransaction();
            $page = new Page();
            $page->title = $request->title;
            if ($request->slug) {
                $page->slug = $request->slug;
            } else {
                $page->slug = StringHelper::createSlug($request->title, 'Page', 'slug', '-');
            }

            if (!is_null($request->banner_image)) {
                $page->banner_image = UploadHelper::upload('banner_image', $request->banner_image, $request->title . '-' . time() . '-banner', 'public/assets/images/pages');
            }

            if (!is_null($request->image)) {
                $page->image = UploadHelper::upload('image', $request->image, $request->title . '-' . time() . '-logo', 'public/assets/images/pages');
            }

            $page->category_id = $request->category_id;
            $page->article_type_id = $request->article_type_id;
            $page->advertisement_ids = $request->advertisement_ids;
            $page->status = $request->status;
            $page->description = $request->description;
            $page->meta_description = $request->meta_description;
            $page->created_at = Carbon::now();
            $page->created_by = Auth::guard('web')->id();
            $page->updated_at = Carbon::now();
            $page->save();

            $this->storeTranslationTermData($page);

            Track::newTrack($page->title, 'New Page has been created');
            DB::commit();
            session()->flash('success', 'New Page has been created successfully !!');
            return redirect()->route('admin.pages.index');
        } catch (\Exception $e) {
            session()->flash('sticky_error', $e->getMessage());
            DB::rollBack();
            return back();
        }
    }

    /**
     * Store Translation Data for Page/Article
     *
     * @param Page $page
     *
     * @return void
     */
    public function storeTranslationTermData(Page $page)
    {
        try {
            $post_data_title = [
                'en'       => $page->title,
                'key'      => 'pt' . $page->id,
                'content'  => 1,
                'page_id'  => $page->id
            ];
            TermRepository::insert_term_after_model_create($post_data_title);

            $post_data_description = [
                'en'       => $page->description,
                'key'      => 'pd' . $page->id,
                'content'  => 1,
                'page_id'  => $page->id
            ];
            TermRepository::insert_term_after_model_create($post_data_description);

            $post_data_meta_description = [
                'en'       => $page->meta_description,
                'key'      => 'pmd' . $page->id,
                'content'  => 1,
                'page_id'  => $page->id
            ];
            TermRepository::insert_term_after_model_create($post_data_meta_description);
        } catch (\Exception $e) {

        }
    }


    /**
     * Translation Create Page
     *
     * @return \Illuminate\Http\Response
     */
    public function createTranslation()
    {
        if (is_null($this->user) || !$this->user->can('translation.page')) {
            return abort(403, 'You are not allowed to access this page !');
        }

        $categories = Category::printCategory(null, 3);
        $pages      = Page::select('id', 'title', 'slug')->get();

        return view('backend.pages.pages.translation', compact('categories', 'pages'));
    }


    /**
     * Translation Store for page/article/blog
     *
     * @return \Illuminate\Http\Response
     */
    public function storeTranslation(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('translation.page')) {
            return abort(403, 'You are not allowed to access this page !');
        }

        $request->validate([
            'id'               => 'required',
            'language_id'      => 'required',
            'title'            => 'nullable|string',
            'description'      => 'nullable|string',
            'meta_description' => 'nullable|string',
        ]);

        $id          = $request->id;
        $language_id = $request->language_id;

        $page     = Page::find($id);
        $language = Language::find($language_id);

        if (!is_null($page) && !is_null($language)) {
            $code = $language->code;
            TermRepository::update_term_by_key('key', 'pt' . $id, $code, $request->title);
            TermRepository::update_term_by_key('key', 'pd' . $id, $code, $request->description);
            TermRepository::update_term_by_key('key', 'pmd' . $id, $code, $request->meta_description);

            Track::newTrack($page->title, 'Translation has been updated.');
            DB::commit();
            session()->flash('success', 'Page translation has been updated successfully !!');
        } else {
            session()->flash('error', 'Page not found !!');
        }
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (is_null($this->user) || !$this->user->can('page.view')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }
        $page = Page::find($id);
        $categories = DB::table('categories')->select('id', 'name')->get();
        return view('backend.pages.pages.show', compact('categories', 'page'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (is_null($this->user) || !$this->user->can('page.edit')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }
        $page = Page::find($id);
        $categories = Category::printCategory($page->category_id);
        $article_types = ArticleType::all();
        $advertisements = Advertisement::select('id', 'title', 'url')->get();
        return view('backend.pages.pages.edit', compact('categories', 'advertisements', 'page', 'article_types'));
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
        if (is_null($this->user) || !$this->user->can('page.edit')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        $page = Page::find($id);
        if (is_null($page)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.pages.index');
        }

        $request->validate([
            'title'  => 'required|max:100',
            'slug'  => 'required|max:100|unique:pages,slug,' . $page->id,
        ]);

        try {
            DB::beginTransaction();
            $page->title = $request->title;
            $page->slug = $request->slug;
            $page->status = $request->status;
            if (!is_null($request->banner_image)) {
                $page->banner_image = UploadHelper::update('banner_image', $request->banner_image, $request->title . '-' . time() . '-banner', 'public/assets/images/pages', $page->banner_image);
            }

            if (!is_null($request->image)) {
                $page->image = UploadHelper::update('image', $request->image, $request->title . '-' . time() . '-logo', 'public/assets/images/pages', $page->image);
            }

            $page->category_id = $request->category_id;
            $page->article_type_id = $request->article_type_id;
            $page->advertisement_ids = $request->advertisement_ids;
            $page->status = $request->status;
            $page->description = $request->description;
            $page->meta_description = $request->meta_description;
            $page->updated_by = Auth::guard('web')->id();
            $page->updated_at = Carbon::now();
            $page->save();

            $this->storeTranslationTermData($page);

            Track::newTrack($page->title, 'Page has been updated successfully !!');
            DB::commit();
            session()->flash('success', 'Page has been updated successfully !!');
            return redirect()->route('admin.pages.index');
        } catch (\Exception $e) {
            session()->flash('sticky_error', $e->getMessage());
            DB::rollBack();
            return back();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (is_null($this->user) || !$this->user->can('page.delete')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        $page = Page::find($id);
        if (is_null($page)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.pages.trashed');
        }
        $page->deleted_at = Carbon::now();
        $page->deleted_by = Auth::guard('web')->id();
        $page->status = 0;
        $page->save();

        session()->flash('success', 'Page has been deleted successfully as trashed !!');
        return redirect()->route('admin.pages.trashed');
    }

    /**
     * revertFromTrash
     *
     * @param integer $id
     * @return Remove the item from trash to active -> make deleted_at = null
     */
    public function revertFromTrash($id)
    {
        if (is_null($this->user) || !$this->user->can('page.delete')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        $page = Page::find($id);
        if (is_null($page)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.pages.trashed');
        }
        $page->deleted_at = null;
        $page->deleted_by = null;
        $page->save();

        session()->flash('success', 'Page has been revert back successfully !!');
        return redirect()->route('admin.pages.trashed');
    }

    /**
     * destroyTrash
     *
     * @param integer $id
     * @return void Destroy the data permanently
     */
    public function destroyTrash($id)
    {
        if (is_null($this->user) || !$this->user->can('page.delete')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }
        $page = Page::find($id);
        if (is_null($page)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.pages.trashed');
        }

        // Remove Images
        UploadHelper::deleteFile('public/assets/images/pages/' . $page->banner_image);
        UploadHelper::deleteFile('public/assets/images/pages/' . $page->image);

        // Delete Page permanently
        $page->delete();

        session()->flash('success', 'Page has been deleted permanently !!');
        return redirect()->route('admin.pages.trashed');
    }

    /**
     * trashed
     *
     * @return view the trashed data list -> which data status = 0 and deleted_at != null
     */
    public function trashed()
    {
        if (is_null($this->user) || !$this->user->can('page.view')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }
        return $this->index(true);
    }
}
