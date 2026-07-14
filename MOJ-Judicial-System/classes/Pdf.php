<?php declare(strict_types=1);
namespace MOJ\JudicialSystem;
use TCPDF;
final class Pdf{public function decision(array $decision,string $path):string{$pdf=new TCPDF('P','mm','A4',true,'UTF-8');$pdf->SetCreator('MOJ Judicial System');$pdf->SetTitle((string)($decision['title']??'Decision'));$pdf->AddPage();$html='<h1>'.Helper::e($decision['title']??'').'</h1><p>'.Helper::e($decision['summary']??'').'</p><hr><div>'.nl2br(Helper::e($decision['full_text']??'')).'</div>';$pdf->writeHTML($html);$pdf->Output($path,'F');return $path;}}
