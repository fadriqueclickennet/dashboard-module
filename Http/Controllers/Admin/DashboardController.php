<?php

namespace Modules\Dashboard\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Modules\Core\Http\Controllers\Admin\AdminBaseController;
use Modules\Dashboard\Repositories\WidgetRepository;
use Modules\User\Contracts\Authentication;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Modules\Blog\Repositories\PostRepository;
use Modules\Blog\Repositories\CategoryRepository;
use Modules\Contact\Repositories\ContactRequestRepository;
use Modules\Contact\Entities\ContactRequest;
use Setting;

class DashboardController extends AdminBaseController
{
    /**
     * @var WidgetRepository
     */
    private $widget;
    /**
     * @var Authentication
     */
    private $auth;

    /**
     * @var PostRepository
     */
    private $post;

    /**
     * @var CategoryRepository
     */
    private $category;

    /**
     * @var ContactRequestRepository
     */
    private $contact;

    /**
     * @param RepositoryInterface $modules
     * @param WidgetRepository $widget
     * @param Authentication $auth
     */
    public function __construct(RepositoryInterface $modules, WidgetRepository $widget, Authentication $auth, ContactRequestRepository $contact, PostRepository $post, CategoryRepository $category)
    {
        parent::__construct();
        $this->bootWidgets($modules);
        $this->widget = $widget;
        $this->auth = $auth;
        $this->post = $post;
        $this->category = $category;
        $this->contact = $contact;
    }

    /**
     * Display the dashboard with its widgets
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $url = $_SERVER['REQUEST_URI'];
        if($url == '/backend'){
            return redirect()->to('/es/backend');
        }

        $posts = $this->post->all()->take(Setting::get('blog::latest-posts-amount'));
        $numPost = $this->post->all()->count();
        $numCategorias = $this->category->all()->count();
        $contacts = ContactRequest::orderBy('created_at', 'desc')->take(5)->get();;
        $numContact =$this->contact->all()->count();

        return view('dashboard::admin.dashboard', compact('posts', 'contacts', 'numPost', 'numCategorias', 'numContact'));
    }

    /**
     * Save the current state of the widgets
     * @param Request $request
     * @return mixed
     */
    public function save(Request $request)
    {
        $widgets = $request->get('grid');

        if (empty($widgets)) {
            return Response::json([false]);
        }

        $this->widget->updateOrCreateForUser($widgets, $this->auth->id());

        return Response::json([true]);
    }

    /**
     * Reset the grid for the current user
     */
    public function reset()
    {
        $widget = $this->widget->findForUser($this->auth->id());

        if (!$widget) {
            return redirect()->route('dashboard.index')->with('warning', trans('dashboard::dashboard.reset not needed'));
        }

        $this->widget->destroy($widget);

        return redirect()->route('dashboard.index')->with('success', trans('dashboard::dashboard.dashboard reset'));
    }

    /**
     * Boot widgets for all enabled modules
     * @param RepositoryInterface $modules
     */
    private function bootWidgets(RepositoryInterface $modules)
    {
        foreach ($modules->allEnabled() as $module) {
            if (! $module->widgets) {
                continue;
            }
            foreach ($module->widgets as $widgetClass) {
                app($widgetClass)->boot();
            }
        }
    }

    /**
     * Require necessary assets
     */
    private function requireAssets()
    {
        $this->assetPipeline->requireJs('lodash.js');
        $this->assetPipeline->requireJs('jquery-ui.js');
        $this->assetPipeline->requireJs('gridstack.js');
        $this->assetPipeline->requireJs('chart.js');
        $this->assetPipeline->requireCss('gridstack.css')->before('main.css');
    }
}
