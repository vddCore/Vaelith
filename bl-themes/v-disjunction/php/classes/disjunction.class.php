<?php

class Disjunction
{
    private $L;
    private $login;

    public function __construct()
    {
        global $L;

        $this->L = $L;
        $this->login = new Login();
    }

    public function canEditPosts(): bool
    {        
        $role = $this->login->role();

        return $this->login->isLogged()
            && ($role  === 'admin'
                || $role === 'author'
                || $role === 'editor');
    }

    public function emitCategoryLink($page)
    {
        if (!empty($page->category())) {
            echo ' • <a href="' . DOMAIN_BASE . 'category/' . $page->category() . '">' . $page->category() . "</a>";
        } else {
            echo ' • no category';
        }
    }

    public function emitMetaData($page)
    {
        echo '<span class="meta-inner">';
        echo $page->user('nickname') . ' - ' . $page->date();
        $this->emitCategoryLink($page);
        echo '</span>';
    }

    public function emitMetaLine($page)
    {
        echo '<span class="meta">';
        $this->emitMetaData($page);
        echo '</span>';
    }

    public function emitReadMoreButton($page)
    {
        echo '<div class="read-more">';
        echo '  <a class="btn btn-primary btn-sm" href="' . $page->permalink() . '" role="button">' . $this->L->get('Read more') . '</a>';
        echo '</div>';
    }

    public function emitPostTitle($page)
    {
        echo '<div class="post-title">';
        echo '  <a href="' . $page->permalink() . '">';
        echo '      <h3 class="title">';
        echo '        ' . $page->title();
        echo '      </h3>';
        echo '  </a>';

        if ($this->canEditPosts()) {
            echo '<a class="post-title-icon" href="admin/edit-content/' . $page->slug() . '"><img src="' . DOMAIN_THEME . 'img/edit.svg" alt="Edit"></img><span class="icon-label">EDIT</span></a>';
        }

        echo '</div>';
    }

    public function emitPostBrief($page)
    {
        $this->emitPostTitle($page);

        if ($page->description()) {
            echo '<p class="page-description">' . $page->description() . '</p>';
        }

        echo '<div class="post-content">';
        echo $page->contentBreak();
        echo '</div>';

        echo '<div class="post-sub">';
        if ($page->readMore()) {
            $this->emitMetaLine($page);
            $this->emitReadMoreButton($page);
        } else {
            $this->emitMetaLine($page);
        }
        echo '</div>';

        echo '<hr style="padding-bottom: 0; margin-bottom: 0;"/>';
    }
}