<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">

  <style>
  .tooltip-main {
    width: 25px;
    height: 15px;
    border-radius: 50%;
    font-weight: 700;
    background: #f3f3f3;
    border: 1px solid #737373;
    color: #737373;
    margin: 4px 121px 0 5px;
    float: right;
    text-align: left !important;
  }

  .tooltip-qm {
    float: left;
    margin: -2px 0px 3px 4px;
    font-size: 12px;
  }

  .tooltip-inner {
    max-width: 536px !important;
    -height: 76px;
    font-size: 12px;
    padding: 10px 15px 10px 20px;
    background: #FFFFFF;
    color: rgb(0, 0, 0, .7);
    border: 1px solid #737373;
    text-align: left;
  }

  .tooltip.show {
    opacity: 1;
  }


  .ques:hover {
    background-color: LightGrey;
  }
</style>

</head>
<body>
  <h1>Quest Interventions Survey Structure</h1>

  <?php

  $string = file_get_contents("./Interventions_rail_suicide.json");
  $surv_obj = json_decode($string, false);

  $surv_elems=$surv_obj->SurveyElements;

  // find the "FL" Element

  $flows=array_values(array_filter($surv_elems, function($elem){ return $elem->Element=="FL"; }));
  $blocksJson=array_values(array_filter($surv_elems, function($elem){ return $elem->Element=="BL"; }));

  $topFlow=$flows[0];

  //echo "Elements: ".count($surv_elems)."\n";
  //echo "Flows: ".count($flows)."\n";
  //echo "Blocks: ".count($blocksJson)."\n";

  $questions=array(); //QuestionID -> Question
  $blocksMap=array(); // BlockID -> Block

  //$tab=".\t";
  $tab="";
  processQuestions($surv_elems);

  processBlocks($blocksJson[0]);

  printFlow($topFlow->Payload, 0);
  //print_r($questions);



  // ------------------------------------------------------------------
  // Questions
  class Question{
    public $id, $text, $spreadsheetID, $responses;
    public $len=100;
    public function __construct( $i,  $t, $s, $r)
    {
      $this->id = $i;
      $this->text = $t;
      $this->spreadsheetID = $s;
      $this->responses = $r;
    }

    public function shortText(){
      if(strlen($this->text)>=$this->len){
        $qtxt = substr($this->text, 0, $this->len/2). ' ... ' . substr($this->text, -$this->len/2);
      } else {$qtxt = $this->text; }
      return $qtxt;
    }
    public function displayText(){
      $qtxt = $this->id.'('.$this->spreadsheetID.')'.':' .$this->shortText();

      if(strlen($this->text)>=$this->len){

        $divText= '<div class="ques" data-toggle="tooltip" data-placement="top" title="'.$this->text.'">' .$qtxt.'</div>';

      } else {
        $divText= '<div class="ques" data-placement="top" >' .$qtxt.'</div>';

      }
      return $divText;
    }
  }

  function processQuestions($surv_elems){
    global $questions;
    foreach($surv_elems as $k => $elem){
      if($elem->Element=="SQ"){
        $pl=$elem->Payload;
        $qtxt=preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($pl->QuestionText)));
        $r=array();
        if($pl->QuestionType=='MC'){
          foreach($pl->Choices as $choiceNo => $choice){
            $r[$choiceNo]=$choice->Display;
          }
        }

        $q=new Question($elem->PrimaryAttribute, $qtxt, $pl->DataExportTag, $r);
        $questions[$elem->PrimaryAttribute]=$q;
      }
    }
  }




  // ------------------------------------------------------------------
  // $blocks

  class Block{
    public $description, $questions;
    public function __construct( $d,  $q)
    {
      $this->description = $d;
      $this->questions = $q;
    }
  }

  function processBlocks($blocks){
    global $blocksMap;

    foreach($blocks->Payload as $k=>$block){
      $ques=array_map(function($el){return $el->QuestionID;}, $block->BlockElements);

      $b=new Block($block->Description, $ques);
      $blocksMap[$block->ID]=$b;
    }
  }

  function printBlock($blockID, $level){

    global $tab, $questions, $blocksMap;
    $str=str_repeat($tab, $level);

    $b=$blocksMap[$blockID];
    echo '<div class="card" style="margin-left: 2rem;">';
    echo "<h5 class='card-title'>Block: ".$b->description."</h5>";
    foreach ($b->questions as $k=>$q){
      $divText=$questions[$q]->displayText();
      echo $divText;
      //echo '<div class="ques" data-toggle="tooltip" data-placement="top" title="'.$questions[$q]->text.'">' .$qtxt.'</div>';
    }
    echo '</div>';
  }

  // ------------------------------------------------------------------
  // Flows

  function printFlow($fl, $level){
    $type=$fl->Type;
    $fid=$fl->FlowID;

    switch($type){
      case 'Root':
      printFlows($fl->Flow, $fl->FlowID, "Root", $level);
      break;
      case 'Block':
      printBlock($fl->ID, $level);
      break;
      case 'Branch':
      printBranchFlow($fl, $level);
      //printFlows($fl->Flow, "Branch", $level);
      break;

      case 'Standard':

      printBlock($fl->ID, $level);
      break;

      case 'EndSurvey':
      echo "<div>$type $fid</div>\n";
      break;
      default:
      echo "<div>**** $type $fid</div>\n";
      break;
    }
  }

  function printBranchFlow($fl, $level){
    echo '<div class="card" style="margin-left: 2rem;">';
    //echo "<h5 class='card-title'>Block: ".$b->description."</h5>";

    printBranchLogic($fl, $level);

    //echo '<div class="card-body">';
    printFlows($fl->Flow, $fl->FlowID, "Branch", $level);

    //echo '</div>';
    echo '</div>';
  }

  function printFlows($fls, $fid, $type, $level){
    if($type=="Root"){
      echo '<div class="collapse.show" id="'.$fid.'">';

    } else {
      echo '<div class="collapse" id="'.$fid.'">';
    }
    echo '<div class="card-body" style="margin-left: 2rem;">';
    //echo "<div>$type </div>\n";
    foreach($fls as $k => $fl){
      printFlow($fl, $level+1);
    }
    echo "</div>";
    echo "</div>";
  }

  function printBranchLogic($fl, $level){
    global $questions;
    $type=$fl->Type;
    $fid=$fl->FlowID;
    $format="q://QID%d/SelectableChoice/%d";

      $logics=$fl->BranchLogic->{'0'};
      $components=array();
      foreach($logics as $k=>$logic){
        if($k!='Type'){
          if($logic->Conjuction !=null){ $components[]=$logic->Conjuction; }

          sscanf($logic->ChoiceLocator, $format, $q, $choiceNo);
          $qid=$logic->QuestionID;
          //$components[]= $qid. "=" .$k.".".($questions[$qid]->responses[$choiceNo]);
          $components[]= $qid. "=" .($questions[$qid]->responses[$choiceNo]);
        }
      }
      $str="$type If (". implode(" ", $components). ")";
      //echo "<h5 class='card-title'>Block: ".$b->description."</h5>";
      //<a class="btn btn-primary" data-toggle="collapse" href="#collapseExample" role="button" aria-expanded="false" aria-controls="collapseExample">

      // echo "<a class='btn btn-primary data-toggle='collapse'
      //  href='#".$fid."' role='button' aria-expanded='false'
      //  aria-controls='collapseExample'>$type $fid If (". implode(" ", $components). ")</a>\n";
      echo '<button style="text-align:left;" class="btn btn-outline-primary" type="button" data-toggle="collapse" aria-pressed="true" data-target="#'.$fid.'" aria-expanded="false" aria-controls="'.$fid.'">
      '.$str.'</button>';
    }

    ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
    <script>
    $(function () {
      $('[data-toggle="tooltip"]').tooltip()
    })
    </script>
  </body>
  </html>
