<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\Models\Wiki;
use App\Models\WikiPage;
use App\Models\Category;
use App\Models\Team;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class WikiController
 *
 * @author Zeeshan Ahmed <ziishaned@gmail.com>
 * @package App\Http\Controllers
 */
class WikiController extends Controller
{
    protected $wiki;

    protected $wikiPage;

    protected $team;

    protected $request;

    protected $category;

    public function __construct(Request $request, Wiki $wiki, Team $team, WikiPage $wikiPage, Category $category) {
        $this->wiki         = $wiki;
        $this->request      = $request;
        $this->wikiPage     = $wikiPage;
        $this->category     = $category;
        $this->team         = $team;
    }

    public function index()
    {
        return $this->wiki->getWikis();
    }

    public function create(Team $team)
    {  
        $categories = $this->category->getTeamCategories($team->id);

        if($categories->count() == 0) {
            return redirect()->route('categories.create', [$team->slug, ])->with([
                'alert' => 'You need to create category before creating wiki!',
                'alert_type' => 'info'
            ]);
        }

        return view('wiki.create', compact('team', 'categories'));
    }

    public function store(Team $team)
    {
        $this->validate($this->request, Wiki::WIKI_RULES);

        $wiki = $this->wiki->saveWiki($this->request->all(), $team->id);

        return redirect()->route('wikis.show', [$team->slug, $wiki->category->slug, $wiki->slug])->with([
            'alert' => 'Wiki successfully created.',
            'alert_type' => 'success'
        ]);
    }

    public function show(Team $team, Category $category, Wiki $wiki)
    {
        $wikiPages = $this->wikiPage->getPages($wiki->id);

        return view('wiki.wiki', compact('wikiPages', 'wiki', 'team', 'category'));
    }
    
    public function getWikiPages()
    {
        $team =  $this->team->where('slug', '=', $this->request->get('team_slug'))->first();
        $category     =  $this->category->where('slug', '=', $this->request->get('category_slug'))->first();
        $wiki         =  $this->wiki->where('slug', '=', $this->request->get('wiki_slug'))->first();
        if($this->request->get('page_slug')) {
            $page     =  $this->wikiPage->where('slug', '=', $this->request->get('page_slug'))->first();
        }

        if($this->request->get('fetch') == 'roots') {
            return $this->wikiPage->getRootPages($team, $category, $wiki);
        } elseif ($this->request->get('fetch') == 'children') {
            return $this->wikiPage->getChildrenPages($team, $category, $wiki, $page);
        } else {
            $wikiPages =  $this->wikiPage->getTreeTo($team, $category, $wiki, $page);
         
            $html = '';
            $this->makePageTree($team, $wiki, $category, $wikiPages, $page->id, $html);
        
            return $html;
        }
    }

    public static function makePageTree($team, $wiki, $category, $wikiPages, $currentPageId, &$html)
    {
        foreach ($wikiPages as $page => $value) {
            foreach($value->getSiblings() as $siblings) {
                if($value->wiki_id == $siblings->wiki_id) {
                    $html .= '<li id="'.$siblings->id.'" data-slug="'.$siblings->slug.'" data-created_at="'. $siblings->created_at .'" class="' . ($siblings->isLeaf() == false ? 'jstree-closed' : '' ) . ' ' . ($siblings->id == $currentPageId ? 'jstree-selected' : '' ) . '"><a href="'.route('pages.show', [$team->slug, $category->slug, $wiki->slug, $siblings->slug]).'">' . $siblings->name . '</a>';
                }
            }
            $html .= '<li id="'.$value->id.'" data-slug="'.$value->slug.'" data-created_at="'. $value->created_at .'" class="' . ($value->isLeaf() == false ? 'jstree-closed' : '' ) . ' ' . ($value->id == $currentPageId ? 'jstree-selected' : '' ) . '"><a href="'.route('pages.show', [$team->slug, $category->slug, $wiki->slug, $value->slug]).'">' . $value->name . '</a>';
            if(!empty($value['children'])) {
                $html .= '<ul>';
                self::makePageTree($team, $wiki, $category, $value['children'], $currentPageId, $html);
                $html .= '</ul></li>';
            }
        }
        return;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param $wikiSlug
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(Team $team, Category $category, Wiki $wiki)
    {
        $categories = $this->category->getTeamCategories($team->id);

        return view('wiki.edit', compact('wiki', 'team', 'categories', 'category'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  string  $wikiSlug
     * @return \Illuminate\Http\Response
     */
    public function update(Team $team, Category $category, Wiki $wiki)
    {        
        $this->wiki->updateWiki($wiki->id, $this->request->all());
        
        return redirect()->route('wikis.show', [$team->slug, $wiki->category->slug, $wiki->slug])->with([
            'alert' => 'Wiki successfully updated.',
            'alert_type' => 'success'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $wikiSlug
     * @return \Illuminate\Http\Response
     */
    public function destroy(Team $team, Category $category, Wiki $wiki)
    {
        $wiki = $this->wiki->deleteWiki($wiki->id);

        return redirect()->route('dashboard', [$team->slug])->with([
            'alert' => 'Wiki successfully deleted.',
            'alert_type' => 'success'
        ]);
    }

    public function reverseArray($data)
    {
        return array_combine(array_keys($data), array_reverse(array_values($data)));
    }

    public function updatePageParent()
    {
        $this->wikiPage->changeParent($this->request->all());
        return response()->json([
            'message' => 'Page parent has been changed.'
        ], Response::HTTP_OK);
    }

    public function overview(Team $team, Category $category, Wiki $wiki)
    {
        return view('wiki.overview', compact('team', 'wiki', 'category'));
    }

    public function permissions(Team $team, Category $category, Wiki $wiki)
    {
        return view('wiki.permissions', compact('team', 'wiki', 'category'));
    }

    public function getWikis(Team $team)
    {
        $wikis = $this->wiki->getTeamWikis($team->id);

        $categories = $this->category->getTeamCategories($team->id);

        return view('wiki.list', compact('team', 'wikis', 'categories'));
    }
}
