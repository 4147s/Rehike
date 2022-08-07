<?php
use \Rehike\Controller\core\AjaxController;
use \Rehike\Model\Comments\CommentThread;
use \Rehike\Request;
use function YukisCoffee\getPropertyAtPath as getProp;

return new class extends AjaxController {
    public function onPost(&$yt, $request) {
        $action = self::findAction();
        if (!@$action) {
            http_response_code(400);
            echo json_encode((object) [
                "errors" => []
            ]);
            die();
        }
        $yt -> page = (object) [];
        
        switch ($action) {
            case "create_comment":
                self::createComment($yt);
                break;
            case "create_comment_reply":
                self::createCommentReply($yt);
                break;
            case "get_comments":
                self::getComments($yt);
                break;
            case "get_comment_replies":
                self::getCommentReplies($yt);
                break;
            case "perform_comment_action":
                self::performCommentAction();
                break;       
        }
    }

    /**
     * Create a comment.
     * 
     * @param $yt Template data.
     */
    private function createComment(&$yt) {
        $this -> template = "ajax/comment_service/create_comment";
        $content = $_POST["content"] ?? null;
        $params = $_POST["params"] ?? null;
        if((@$content == null) | (@$params == null)) {
            http_response_code(400);
            echo json_encode((object) [
                "errors" => []
            ]);
            die();
        }

        $response = Request::innertubeRequest("comment/create_comment", (object) [
            "commentText" => $_POST["content"],
            "createCommentParams" => $_POST["params"]
        ]);
        $ytdata = json_decode($response);
        $data = $ytdata -> actions[1] -> createCommentAction -> contents -> commentThreadRenderer ?? null;
        $yt -> page = CommentThread::commentThreadRenderer($data);
    }

    /**
     * Create a reply to a comment.
     * 
     * @param $yt Template data.
     */
    private function createCommentReply(&$yt) {
        $this -> template = "ajax/comment_service/create_comment_reply";
        $content = $_POST["content"] ?? null;
        $params = $_POST["params"] ?? null;
        if((@$content == null) | (@$params == null)) {
            http_response_code(400);
            echo json_encode((object) [
                "errors" => []
            ]);
            die();
        }

        $response = Request::innertubeRequest("comment/create_comment_reply", (object) [
            "commentText" => $_POST["content"],
            "createReplyParams" => $_POST["params"]
        ]);
        $ytdata = json_decode($response);
        $data = $ytdata -> actions[1] -> createCommentReplyAction -> contents -> commentRenderer ?? null;
        $yt -> page = CommentThread::commentRenderer($data, true);
    }

    /**
     * Get comments for continuation or
     * reload (for changing sort).
     * 
     * @param $yt Template data.
     */
    private function getComments(&$yt) {
        $this -> template = "ajax/comment_service/get_comments";
        $ctoken = $_POST["page_token"] ?? null;
        if(!@$ctoken) {
            http_response_code(400);
            echo json_encode((object) [
                "errors" => []
            ]);
            die();
        }

        $response = Request::innertubeRequest("next", (object) [
            "continuation" => $_POST["page_token"]
        ]);
        $ytdata = json_decode($response);
        try {
            $data = getProp($ytdata, "onResponseReceivedEndpoints[0].appendContinuationItemsAction");
        } catch (\YukisCoffee\GetPropertyAtPathException $e) {
            try {
                $data = getProp($ytdata, "onResponseReceivedEndpoints[1].reloadContinuationItemsCommand");
            } catch(\YukisCoffee\GetPropertyAtPathException $e) {
                echo json_encode((object) [
                    "error" => "Failed to get comment continuation/sort"
                ]);
                exit();
            }
        }

        $yt -> page = CommentThread::bakeComments($data);
    }

    /**
     * Get comment replies.
     * 
     * @param $yt Template data.
     */
    private function getCommentReplies(&$yt) {
        $this -> template = "ajax/comment_service/get_comment_replies";
        $ctoken = $_POST["page_token"] ?? null;
        if(!@$ctoken) {
            http_response_code(400);
            echo json_encode((object) [
                "errors" => []
            ]);
            die();
        }
        
        $response = Request::innertubeRequest("next", (object) [
            "continuation" => $_POST["page_token"]
        ]);
        $ytdata = json_decode($response);
        try {
            $data = getProp($ytdata, "onResponseReceivedEndpoints[0].appendContinuationItemsAction");
        } catch(\YukisCoffee\GetPropertyAtPathException $e) {
            echo json_encode((object) [
                "error" => "Failed to get comment replies"
            ]);
            exit();
        }
        $yt -> page = CommentThread::bakeReplies($data);
    }

    /**
     * Perform a comment action
     * (Like, dislike, heart, etc.)
     */
    private function performCommentAction() {
        $this -> useTemplate = false;

        $response = Request::innertubeRequest("comment/perform_comment_action", (object) [
            "actions" => [
                $_POST["action"]
            ]
        ]);
        $ytdata = json_decode($response);

        if (@$ytdata -> actionResults[0] -> status == "STATUS_SUCCEEDED") {
            echo json_encode((object) [
                "response" => "SUCCESS"
            ]);
        } else {
            http_response_code(400);
            echo json_encode((object) [
                "errors" => []
            ]);
        }
    }
};