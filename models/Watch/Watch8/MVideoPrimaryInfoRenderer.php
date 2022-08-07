<?php
namespace Rehike\Model\Watch\Watch8;

use Rehike\TemplateFunctions;
use Rehike\Model\Common\Subscription\MSubscriptionActions;
use Rehike\Model\Common\MButton;
use Rehike\Model\Common\MToggleButton;
use Rehike\Model\Clickcard\MSigninClickcard;
use Rehike\ConfigManager\ConfigManager;
use Rehike\Signin\API as SignIn;

include_once "controllers/utils/extractUtils.php";

use \ExtractUtils;

/**
 * Implement the model for the primary info renderer.
 * 
 * This is generally a restructuring of the Kevlar formatted data
 * returned by InnerTube with WEB v2 as parameters.
 * 
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 * @author The Rehike Maintainers
 */
class MVideoPrimaryInfoRenderer
{
    /** @var string */
    public $title = "";

    /** @var string */
    public $viewCount = "";

    /** @var object[] */
    public $superTitle;

    /** @var mixed */
    public $badges;

    /** @var MOwner */
    public $owner;

    /** @var MActionButton[] */
    public $actionButtons = [];

    /** @var MLikeButtonRenderer */
    public $likeButtonRenderer = [];

    // dataHost == get_called_class of caller
    public function __construct($dataHost, $videoId)
    {
        $info = &$dataHost::$primaryInfo ?? null;

        if (!is_null($info))
        {
            $this->title = $info->title ?? null;

            // Also set title of the whole page from this property
            $dataHost::$title = TemplateFunctions::getText($this->title);

            $this->viewCount = (true === ConfigManager::getConfigProp("noViewsText")) ? ExtractUtils::isolateViewCnt(TemplateFunctions::getText($info->viewCount->videoViewCountRenderer->viewCount)) : TemplateFunctions::getText($info->viewCount->videoViewCountRenderer->viewCount) ?? null;
            $this->badges = $info->badges ?? null;
            $this->superTitle = isset($info->superTitleLink) ? new MSuperTitle($info->superTitleLink) : null;
            $this->likeButtonRenderer = new MLikeButtonRenderer($dataHost, $info->videoActions->menuRenderer, $videoId);
            $this->owner = new MOwner($dataHost);

            // Create action butttons
            $orderedButtonQueue = [];

            // Share button should always be built unless this is a
            // Kids video
            if (!$dataHost::$isKidsVideo)
            {
                $orderedButtonQueue[] = MActionButton::buildShareButton();
            }

            // wtf
            if (!$dataHost::$isKidsVideo)
            foreach (@$info->videoActions->menuRenderer->topLevelButtons as $b)
            if (isset($b->buttonRenderer) && ($button = @$b->buttonRenderer))
            switch ($button->icon->iconType)
            {
                case "PLAYLIST_ADD":
                    // Push to the beginning of the array
                    // since this should always come first
                    array_unshift($orderedButtonQueue, MActionButton::buildW8AddtoButton($videoId));
                    break;
                case "FLAG":
                    $orderedButtonQueue[] = MActionButton::buildReportButton();
                    break;
            }

            $this->actionButtons = &$orderedButtonQueue;
        }
    }
}

/**
 * Defines the video owner information, which appears in the bottom
 * left corner of the primary info renderer.
 */
class MOwner
{
    /** @var string */
    public $title = "";

    /** @var mixed[] */
    public $thumbnail;

    /** @var mixed[] */
    public $badges;

    /** @var object */
    public $navigationEndpoint;

    /**
     * Defines the subscription actions.
     * 
     * These include the subscribe button, the notifications button,
     * and the count at the end.
     *  
     * @var MSubscriptionActions 
     */
    public $subscriptionButtonRenderer;

    public function __construct($dataHost)
    {
        $secInfo = &$dataHost::$secondaryInfo;
        $info = $secInfo->owner->videoOwnerRenderer;
        

        if (isset($info))
        {
            $this->title = $info->title ?? null;
            $this->thumbnail = $info->thumbnail ?? null;
            $this->badges = $info->badges ?? null;
            $this->navigationEndpoint = $info->navigationEndpoint ?? null;

            // Subscription button
            $subscribeCount = isset($info->subscriberCountText)
                ? ExtractUtils::isolateSubCnt(TemplateFunctions::getText($info->subscriberCountText))
                : null
            ;

            // Build the subscription button from the InnerTube data.
            $this->subscriptionButtonRenderer = MSubscriptionActions::fromData(
                $secInfo -> subscribeButton -> subscribeButtonRenderer, $subscribeCount
            );
        }
    }
}

/**
 * Defines an abstract watch action button.
 * 
 * These are the buttons for watch interaction, such
 * as add to and share.
 * 
 * TODO(dcooper): more action button
 */
class MActionButton extends MButton
{
    // Define default button properties.
    public $style = "opacity";
    public $hasIcon = true;
    public $noIconMarkup = true;
    public $class = [
        "pause-resume-autoplay"
    ];

    public function __construct($data)
    {
        parent::__construct([]);

        // Set the button data as provided.
        $this->setText($data["label"] ?? "");
        $this->tooltip = $data["tooltip"] ?? $data["label"];

        // Push provided attributes if they exist.
        if (@$data["attributes"])
        foreach ($data["attributes"] as $key => $value)
        {
            $this->attributes += [$key => $value];
        }

        if (@$data["class"])
        {
            if (is_string($data["class"]))
            {
                $this->class[] = $data["class"];
            }
            else
            {
                /*
                 * BUG (kirasicecreamm): This used += operator to
                 * append the arrays, which is useful for associative,
                 * but not numerical arrays.
                 * 
                 * This caused it to ignore the 0th item and so on
                 * as it conflicted with the preexisting index in
                 * this parent class.
                 */
                $this->class = array_merge($this->class, $data["class"]);
            }
        }

        if (@$data["actionPanelTrigger"])
        {
            $this->class[] = "action-panel-trigger";
            $this->class[] = "action-panel-trigger-" . $data["actionPanelTrigger"];
            $this->attributes["trigger-for"] = "action-panel-" . $data["actionPanelTrigger"];
            
            // Watch8 only: (if doing w7 in the future edit this)
            $this->attributes["button-toggle"] = "true";
        }

        if (@$data["clickcard"])
        {
            $this->clickcard = &$data["clickcard"];
        }

        if (@$data["videoActionsMenu"])
        {
            $this->videoActionsMenu = &$data["videoActionsMenu"];
        }
    }

    /**
     * Build a watch8 add to playlists button, or its signed out
     * stub.
     * 
     * @return void
     */
    public static function buildW8AddtoButton($videoId)
    {
        $buttonCfg = [
            "label" => "Add to", // TODO: i18n
            "class" => []
        ];

        if (!SignIn::isSignedIn())
        {
            $buttonCfg += [
                "clickcard" => new MSigninClickcard(
                    "Want to watch this again later?",
                    "Sign in to add this video to a playlist."
                ),
                "attributes" => [ // Clickcard attributes
                    "orientation" => "vertical",
                    "position" => "bottomleft"
                ]
            ];
        }
        else
        {
            $buttonCfg += [
                "videoActionsMenu" => (object)[
                    "contentId" => "yt-uix-videoactionmenu-menu",
                    "videoId" => $videoId
                ]
            ];

            $buttonCfg["class"] += [
                "yt-uix-menu-trigger",
                "yt-uix-videoactionmenu-button"
            ];
        }

        $buttonCfg["class"][] = "addto-button";

        return new self($buttonCfg);
    }

    /**
     * Build a watch7 or watch8 share button.
     * 
     * @return void
     */
    public static function buildShareButton()
    {
        return new self([
            "label" => "Share",
            "actionPanelTrigger" => "share"
        ]);
    }

    /**
     * Build a watch8 report button, which is used on livestreams.
     * 
     * If the video is not a livestream, then the report button appears in
     * the more button's menu instead.
     * 
     * @return void
     */
    public static function buildReportButton()
    {
        return new self([
            "label" => "Report",
            "class" => "report-button",
            "actionPanelTrigger" => "report",
            "clickcard" => new MSigninClickcard(
                "Need to report the video?",
                "Sign in to report inappropriate content."
            ),
            "attributes" => [ // Clickcard attributes
                "orientation" => "horizontal",
                "position" => "topright"
            ]
        ]);
    }
}

/**
 * Defines the like button (and dislike button) container.
 * 
 * This stores two copies of both the like and dislike buttons, which are
 * used for their individual activation states. This is how hitchhiker
 * handled this.
 */
class MLikeButtonRenderer
{
    /** @var MLikeButton */
    public $likeButton;
    public $activeLikeButton;

    /** @var MDislikeButton */
    public $dislikeButton;
    public $activeDislikeButton;

    /** @var MSparkbars */
    public $sparkbars;

    public function __construct($dataHost, &$info, &$videoId)
    {
        $origLikeButton = &$info->topLevelButtons[0]->toggleButtonRenderer;
        $origDislikeButton = &$info->topLevelButtons[1]->toggleButtonRenderer;

        $likeA11y = $origLikeButton->accessibility->label;
        $dislikeA11y = $origDislikeButton->accessibility->label;

        $isLiked = $origLikeButton->isToggled;
        $isDisliked = $origDislikeButton->isToggled;

        // Extract like count from like count string
        $likeCount = ExtractUtils::isolateLikeCnt($likeA11y ?? "");
        
        if (is_numeric(str_replace(",", "", $likeCount)))
            $likeCountInt = (int)str_replace(",", "", $likeCount);

        // Since December 2021, dislikes are unavailable.
        $dislikeCount = "";

        // Account for RYD API data if it exists
        if ($dataHost::$useRyd && "" !== $likeCount)
        {
            $rydData = &$dataHost::$rydData;

            $dislikeCount = number_format($rydData -> dislikes);

            $dislikeCountInt = (int)$rydData -> dislikes;

            $this->sparkbars = new MSparkbars($likeCountInt, $dislikeCountInt);
        }

        $this->likeButton = new MLikeButton(@$likeCountInt, $likeA11y, !$isLiked, $videoId);
        $this->activeLikeButton = new MLikeButton(@$likeCountInt, $likeA11y, $isLiked, $videoId, true);
        $this->dislikeButton = new MDislikeButton(@$dislikeCountInt, $dislikeA11y, !$isDisliked, $videoId);
        $this->activeDislikeButton = new MDislikeButton(@$dislikeCountInt, $dislikeA11y, $isDisliked, $videoId, true);
    }
}

/**
 * Define an abstract actual "like button" button (also used for dislikes).
 */
class MLikeButtonRendererButton extends MToggleButton
{
    protected $hideNotToggled = true;

    public $style = "opacity";
    public $hasIcon = true;
    public $noIconMarkup = true;
    public $attributes = [
        "orientation" => "vertical",
        "position" => "bottomright",
        "force-position" => "true"
    ];

    public function __construct($type, $active, $count, $state)
    {
        parent::__construct($state);

        $class = "like-button-renderer-" . $type;
        $this->class[] = $class;
        $this->class[] = $class . "-" . ($active ? "clicked" : "unclicked");
        if ($active)
            $this->class[] = "yt-uix-button-toggled";

        if (!is_null($count))
            $this->setText(number_format($count));
    }
}

/**
 * Define the like button.
 */
class MLikeButton extends MLikeButtonRendererButton
{
    public function __construct($likeCount, $a11y, $isLiked, $videoId, $active = false)
    {
        if ($active) $likeCount++;

        $this->accessibilityAttributes = [
            "label" => $a11y
        ];

        $this->tooltip = "I like this"; // TODO: i18n
        
        if ($active)
            $this->tooltip = "Unlike";

        $signinMessage = "Like this video?";
        $signinDetail = "Sign in to make your opinion count.";

        // Store a reference to the current sign in state.
        $signedIn = SignIn::isSignedIn();

        if ($signedIn) {
            $this -> attributes["post-action"] = "/service_ajax?name=likeEndpoint";
            $this -> class[] = "yt-uix-post-anchor";
        }

        if (!$signedIn && !$active) {
            $this->clickcard = new MSigninClickcard($signinMessage, $signinDetail);
        } elseif ($signedIn && !$active) {
            $this -> attributes["post-data"] = "action=like&id=" . $videoId;
        } elseif ($signedIn && $active) {
            $this -> attributes["post-data"] = "action=removelike&id=" . $videoId;
        }

        parent::__construct("like-button", $active, $likeCount, $isLiked);
    }
}

/**
 * Define the dislike button.
 */
class MDislikeButton extends MLikeButtonRendererButton
{
    public function __construct($dislikeCount, $a11y, $isDisliked, $videoId, $active = false)
    {
        if ($active) $dislikeCount++;

        $this->accessibilityAttributes = [
            "label" => $a11y
        ];

        $this->tooltip = "I dislike this"; // TODO: i18n

        $signinMessage = "Don't like this video?";
        $signinDetail = "Sign in to make your opinion count.";

        // Store a reference to the current sign in state.
        $signedIn = SignIn::isSignedIn();

        if ($signedIn) {
            $this -> attributes["post-action"] = "/service_ajax?name=likeEndpoint";
            $this -> class[] = "yt-uix-post-anchor";
        }

        if (!$signedIn && !$active) {
            $this->clickcard = new MSigninClickcard($signinMessage, $signinDetail);
        } elseif ($signedIn && !$active) {
            $this -> attributes["post-data"] = "action=dislike&id=" . $videoId;
        } elseif ($signedIn && $active) {
            $this -> attributes["post-data"] = "action=removedislike&id=" . $videoId;
        }

        parent::__construct("dislike-button", $active, $dislikeCount, $isDisliked);
    }
}

/**
 * Define the sparkbars (sentiment bars; like to dislike ratio bar).
 */
class MSparkbars
{
    /** @var float */
    public $likePercent = 50;
    
    /** @var float */
    public $dislikePercent = 50;
    
    public function __construct($likeCount, $dislikeCount)
    {
        if (0 != $likeCount + $dislikeCount)
        {
            $this->likePercent = ($likeCount / ($likeCount + $dislikeCount)) * 100;
            $this->dislikePercent = 100 - $this->likePercent;
        }
    }
}

/**
 * Define the super title (links that appear above the title, such as hashtags).
 */
class MSuperTitle
{
    public $items = [];

    public function __construct($superTitleLink)
    {
        foreach ($superTitleLink->runs as $run) if (" " != $run->text)
        {
            $this->items[] = (object)[
                "text" => preg_replace("/For/", "for", preg_replace("/On/", "on", ucwords(strtolower($run->text)))),
                "url" => TemplateFunctions::getUrl($run)
            ];
        }
    }
}