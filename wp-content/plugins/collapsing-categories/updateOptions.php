<?php
      $title=$new_instance['title'];
      $title_link=$new_instance['title_link'];
      if ($new_instance['linkToCat']) {
        $linkToCat = 1;
      } else {
        $linkToCat = 0;
      }
      if (isset($new_instance['showPostCount']) ) {
        $showPostCount = 1;
      } else {  
        $showPostCount = 0;
      }
      if (isset($new_instance['showPostDate']) ) {
        $showPostDate = 1;
      } else {  
        $showPostDate = 0;
      }
      $catSortOrder= 'ASC' ;
      if ($new_instance['catSortOrder'] == 'DESC') {
        $catSortOrder= 'DESC' ;
      }
      if ($new_instance['catSort'] == 'catName') {
        $catSort= 'catName' ;
      } elseif ($new_instance['catSort'] == 'catId') {
        $catSort= 'catId' ;
      } elseif ($new_instance['catSort'] == 'catSlug') {
        $catSort= 'catSlug' ;
      } elseif ($new_instance['catSort'] == 'catOrder') {
        $catSort= 'catOrder' ;
      } elseif ($new_instance['catSort'] == 'catCount') {
        $catSort= 'catCount' ;
      } elseif ($new_instance['catSort'] == '') {
        $catSort= '' ;
        $catSortOrder= '' ;
      }
      $postSortOrder= 'ASC' ;
      if ($new_instance['postSortOrder'] == 'DESC') {
        $postSortOrder= 'DESC' ;
      }
      if ($new_instance['postSort'] == 'postTitle') {
        $postSort= 'postTitle' ;
      } elseif ($new_instance['postSort'] == 'postId') {
        $postSort= 'postId' ;
      } elseif ($new_instance['postSort'] == 'postComments') {
        $postSort= 'postComments' ;
      } elseif ($new_instance['postSort'] == 'postDate') {
        $postSort= 'postDate' ;
      } elseif ($new_instance['postSort'] == 'postOrder') {
        $postSort= 'postOrder' ;
      } elseif ($new_instance['postSort'] == '') {
        $postSort= '' ;
        $postSortOrder= '' ;
      }
      $expand= $new_instance['expand'];
      $customExpand= $new_instance['customExpand'];
      $customCollapse= $new_instance['customCollapse'];
      $taxonomy= $new_instance['taxonomy'];
      $post_type= $new_instance['post_type'];
      $olderThan= $new_instance['olderThan'];
      $inExclude= 'include' ;
      if($new_instance['inExclude'] == 'exclude') {
        $inExclude= 'exclude' ;
      }
      $postDateAppend= 'after' ;
      if($new_instance['postDateAppend'] == 'before') {
        $postDateAppend= 'before' ;
      }
      $debug = 0;
      if (isset($new_instance['debug'])) {
        $debug = 1;
      }
      $showEmptyCat = 0;
      if (isset($new_instance['showEmptyCat'])) {
        $showEmptyCat = 1;
      }
      $useCookies = 1;
      if (!isset($new_instance['useCookies'])) {
        $useCookies= 0 ;
      }
      $expandCatPost = 1;
      if (!isset($new_instance['expandCatPost'])) {
        $expandCatPost = 0;
      }
      $showTopLevel = 1;
      if (!isset($new_instance['showTopLevel'])) {
        $showTopLevel= 0;
      }
      $postsBeforeCats = 0;
      if (isset($new_instance['postsBeforeCats'])) {
        $postsBeforeCats= 1 ;
      }
      $addMisc = 0;
      if (isset($new_instance['addMisc'])) {
        $addMisc= 1 ;
      }

      $useAJax = 0;
      if (isset($new_instance['useAjax'])) {
        $useAjax = 1;
      }
      if( isset($new_instance['accordion'])) {
        $accordion= 1;
      } else {
        $accordion = 0;
      }
      $catfeed=addslashes($new_instance['catfeed']);
      $inExcludeCats=addslashes($new_instance['inExcludeCats']);
      $postDateFormat=addslashes($new_instance['postDateFormat']);
      $defaultExpand=addslashes($new_instance['defaultExpand']);
      $postTitleLength=addslashes($new_instance['postTitleLength']);
      $addMiscTitle=addslashes($new_instance['addMiscTitle']);
      if ($new_instance['showPosts']) {
        $showPosts= 1;
      } else {
        $showPosts= 0;
      }
/* update style settings */
      $style = $new_instance['style'];
      $instance = compact(
          'title','showPostCount','catSort','catSortOrder','defaultExpand',
          'expand','inExclude','inExcludeCats','postSort','postSortOrder',
          'debug', 'showPosts', 'customExpand', 'customCollapse',
          'taxonomy', 'linkToCat', 'showPostDate', 'postDateFormat',
          'showEmptyCat', 'post_type', 'style', 'accordion', 'title_link',
          'olderThan', 'postDateAppend','postTitleLength', 'useCookies',
          'showTopLevel', 'postsBeforeCats', 'catfeed', 'addMisc',
          'addMiscTitle', 'expandCatPost', 'useAjax');

?>
