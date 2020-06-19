<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
<style>
table {
  border-collapse: collapse;
}

table, th, td {
  border-top: 1px solid grey;
}

tr:hover {background-color: LightGrey;}
</style>

  </head>
  <body>
    <h1>Quest Interventions Survey Structure</h1>

  <table>
  <?php
$string = file_get_contents("./Interventions_rail_suicide.json");
$surv_obj = json_decode($string, false);

$surv_elems=$surv_obj->SurveyElements;

$qObjs=array_values(array_filter($surv_elems, function($elem){ return $elem->Element=="SQ"; }));

$questions=array(); //QuestionID -> Question

$quesToID=array();// String -> Array(QuestionID)
$qBlocks=array();//QuestionID -> BlockID

// ---------------------------------------------------------------------
// Questions

class Question{
  public $id, $text, $spreadsheetID, $responses;
  public function __construct( $i,  $t, $s, $r)
  {
    $this->id = $i;
    $this->text = $t;
    $this->spreadsheetID = $s;
    $this->responses = $r;
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
processQuestions($surv_elems);

// ---------------------------------------------------------------------
// Flows

class Flow{
  public $flowid;
  public $up;
  public static $blocks=array();// Map BlockID->BlockFlow
  public function __construct($fid, $p) {
    $this->flowid=$fid;

    $this->up=$p;
  }

  public static function constructFlows($fs, $p){

    $flows=array();
    foreach($fs as $f){
      $flows[]=Flow::constructFlow($f, $p);
    }
    return $flows;
  }

  public static function constructFlow($flow, $p){
    $type=$flow->Type;
    $id=$flow->FlowID;

    switch ($flow->Type){
      case "Root":
      return new RootFlow($id, $p, $flow->Flow);
      break;
      case "Branch":
      return new BranchFlow($id, $p, $flow->Flow, $flow->BranchLogic);
      break;
      case "Standard":
      return new BlockFlow($id, $p, $flow->ID);
      break;
      case "Block":
      return new BlockFlow($id, $p, $flow->ID);
      break;
      case "EndSurvey":
      return new EndFlow($id, $p);
      break;
    }
  }

  public function ancestors(){
    $anc=array();
    if($this->parent!=0){
      $anc=$this->parent->ancestors();
    }
    $anc[]=$this;
    return $anc;
  }
  public function ancestorConditions(){
    $anc=array();
    if($this->up!=null){
      $anc=$this->up->ancestorConditions();
    }
    return $anc;
  }
  public static function getContainingConditions($bid){
    //echo "getContainingConditions $bid\n";
    if(array_key_exists($bid, self::$blocks)){
      return self::$blocks[$bid]->ancestorConditions();
    } else { return array(); }
  }
}

class RootFlow extends Flow{
  public $flows;
  public function __construct($fid, $p, $fs) {
    parent::__construct($fid, $p);
    $this->flows=Flow::constructFlows($fs, $this);// $this
  }
}

class BranchFlow extends RootFlow{
  public $logic;
  public function __construct($fid, $p, $fs, $bl) {
    parent::__construct($fid, $p, $fs);
    $bls=branchLogicString($bl);
    $this->logic=$bls;
  }

  public function ancestorConditions(){
    $anc=array();
    if($this->up!=null){
      $anc=$this->up->ancestorConditions();
    }
    $anc[]=$this->logic;
    return $anc;
  }
}

class BlockFlow extends Flow{
  public $block; // The actual block? or an ID?
  public function __construct($fid, $p, $bid) {
    parent::__construct($fid, $p);
    $this->block=$bid;
    //echo "Creating block flow $fid $bid\n";
    //print_r($this);
    parent::$blocks[$bid]=$this;
    //prinr_r(parent::$blocks);

  }
}
class EndFlow extends Flow{
}


// ---------------------------------------------------------------------
// Functions

function branchLogicString($bls){
  global $questions;
  //$type=$fl->Type;
  //$fid=$fl->FlowID;
  $format="q://QID%d/SelectableChoice/%d";

    $logics=$bls->{'0'};
    $components=array();
    foreach($logics as $k=>$logic){
      if($k!='Type'){
        if(property_exists($logic, 'Conjuction') && $logic->Conjuction!= null){ $components[]=$logic->Conjuction; }

        sscanf($logic->ChoiceLocator, $format, $q, $choiceNo);
        $qid=$logic->QuestionID;

        //$components[]= $qid. "=XXX" ;
        $components[]= $qid. "=" .($questions[$qid]->responses[$choiceNo]);
      }
    }
    $str=" If (". implode(" ", $components). ")";

    return $str;
  }

// could put this in processQuestions()
  foreach($qObjs as $i=>$q){
    $qid=$q->Payload->QuestionID;
    $txt=preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($q->Payload->QuestionText)));

    if(!array_key_exists($txt, $quesToID)){
      $quesToID[$txt]=array();
    }
    $quesToID[$txt][]=$qid;

  }

  $flow=array_values(array_filter($surv_elems, function($elem){ return $elem->Element=="FL"; }))[0];
  $bs=array_values(array_filter($surv_elems, function($elem){ return $elem->Element=="BL"; }))[0];

  foreach($bs->Payload as $i=> $b){
    //print_r($b);
    //echo $b->BlockID."*\n";
    foreach ($b->BlockElements as $e){
      $qBlocks[$e->QuestionID]=$b->ID;
    }
  }

  $f=Flow::constructFlow($flow->Payload, null);

  //print_r(Flow::$blocks);
  //print_r($f);

  foreach($quesToID as $qtext => $qids){
    $len=100;
    if(strlen($qtext)>=$len){
      $txt = substr($qtext, 0, $len/2). ' ... ' . substr($qtext, -$len/2);
    } else {$txt = $qtext; }
    echo "<tr><td colspan='2'><h5>$txt</h5></td></tr>\n";

    foreach ($qids as $qid){
          echo "<tr>\n";
      $condString=".";
      if(array_key_exists($qid, $qBlocks)){ // if not, don't wantto output anything for this question.
        $conds=Flow::getContainingConditions($qBlocks[$qid]);
        $condString=implode(" & ", $conds);
      }
      //echo "<td></td><td>$qid</td><td>".$qBlocks[$qid]." </td><td>  ".$condString."</td>\n";
      echo "<td style='padding-left:2em;vertical-align:top;'>$qid</td><td>  ".$condString."</td>\n";

          echo "<tr>\n";
    }

  }

  //print_r(count($quesToID));



  ?>

    </table>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>

  </body>
  </html>
