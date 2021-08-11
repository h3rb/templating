<?php

abstract class TemplateException extends Exception {}
class SyntaxErrorException extends TemplateException {}
class UndefinedSymbolException extends TemplateException {}

define('TEMPLATE_BLOCK_ERROR',-1);
define('TEMPLATE_BLOCK_NONE',0);
define('TEMPLATE_BLOCK_VAR',1);
define('TEMPLATE_BLOCK_EACH',2);
define('TEMPLATE_BLOCK_IF',3);
define('TEMPLATE_BLOCK_ELSE',4);
define('TEMPLATE_BLOCK_ELSEIF',5);
define('TEMPLATE_BLOCK_ENDIF',6);
define('TEMPLATE_BLOCK_ENDLOOP',7);

class TemplateIteratorStack {
	
};

class TemplateParser {
	var $inner, $outer, $pre, $post, $type, $pieces;
	public function __construct( $type=TEMPLATE_BLOCK_NONE, $outer=NULL ) {
		$this->type=$type;
		$this->inner=NULL;
		$this->pre="";
		$this->post="";
		$this->pieces=array();
	}
	public function Render( $template, $values ) {
		$i=0;
		return $this->Parse($i,$template,$values);
	}
	
	private static function AccumulateToBlockStart( &$i, $len, $template ) {		
        $a="";
		while ( 1 ) {
			$c = substr($template,$i,1);
			$i++;
			if ( $c == '#' || $i >= $len ) { // Block Start?
				$d=substr($template,$i,1);
				$i++;
				if ( $d == '%' || $i >= $len ) { // Block Start.
				   return $a;
				} else $a.=$d;
			} else $a.=$c;
		}
	}
	
	private static function AccumulateToBlockEnd( &$i, $len, $template ) {		
        $a="";
		while ( 1 ) {
			$c = substr($template,$i,1);
			$i++;
			if ( $c == '%' || $i >= $len ) { // Block Start?
				$d=substr($template,$i,1);
				$i++;
				if ( $d == '#' || $i >= $len ) { // Block Start.
				   return $a;
				} else $a.=$d;
			} else $a.=$c;
		}
	}
	
	private static function QueryBlockType( $string ) {
		$string=trim($string);
		if ( starts_with($string,"if ") ) return TEMPLATE_BLOCK_IF;
		if ( starts_with($string,"each ") ) return TEMPLATE_BLOCK_EACH;
		if ( starts_with($string,"else if ") || starts_with($string,"elif ") || starts_with($string,"elseif ") ) return TEMPLATE_BLOCK_ELSEIF;
		if ( starts_with($string,"else ") ) return TEMPLATE_BLOCK_ELSE;
		if ( is($string,"endif") ) return TEMPLATE_BLOCK_ENDIF;
		if ( is($string,"endloop") || is($string,"end") ) return TEMPLATE_BLOCK_ENDLOOP;
		return TEMPLATE_BLOCK_ERROR;
	}
	
	static public function EnTemplaten( $string ) { return '#%'.$string.'%#'; }

    static public function GetValue( $reference, $values ) {
		$scopes = words($reference,'.');
		$scope = &$values;
		$value=NULL;
		$error=FALSE;
		foreach ( $scopes as $s ) {
			if ( is_null($scope) ) { $error=TRUE; break; }
			if ( isset($scope[$s]) ) {
				$value=$scope[$s];
				if ( is_array($value) ) $scope=&$values[$s];
				else $scope=NULL;
			} else { $error=TRUE; break; }
		}
		if ( $error !== FALSE ) return array(TRUE,"!?$reference?!");
		return $value;
	}
	
	
	static public function FindSubVariableReference( $reference ) {
		if ( strlen($reference) === 0 ) return '';
		$left = strpos($reference,'%');
		$right = strrpos($reference,'%');
		if ( $left === $right ) return $reference;
		$pre=substr($reference,0,$left);
		$middle=substr($reference,$left+1,-(strlen($reference)-$right-1));
		$post=substr($reference,$right+1);
		return array($pre,$middle,$post,$left,$right);
	}
	
	static public function FindFinalReference( $reference, $values, $scopes ) {
		$sub = TemplateParser::FindSubVariableReference($reference);
		if ( is_array($sub) ) {
			$result=TemplateParser::GetVariable($sub[1],$values,$scopes);
			if ( $result === NULL ) return $reference; // We couldn't find the reference.
			return $sub[0] . $result . $sub[2];
		}
		return $sub;
	}
	
	static public function GetVariable( $reference, $values, $scopes ) {
		$reference=trim($reference);
		if ( strlen($reference) === 0 ) return '';
		if ( is(substr($reference,0,1),"#") ) { // Prefixing a variable with a # means "use global not scoped"
		 $reference=substr($reference,1);
		 $reference=trim($reference);
		 if ( strlen($reference) === 0 ) return '';
		 $parsed=TemplateParser::FindFinalReference($reference,$values,$scopes);
		 $result=TemplateParser::GetValue($parsed,$values);
		 if ( is_array($result)
		   && count($result)==2
      	   && $result[0]===TRUE
		   && substr($result[1],0,2) == "!?"
		   && substr($result[1],strlen($result[1])-2-1,2) == '?!'
		   && is(substr($result[1],2,strlen($result[1])-4),$pre.$reference) ) {
		  return NULL;
		 }
		 return $result;
		}
		if ( is(substr($reference,0,1),"$") ) { // Prefixing a variable with a $ means "use scoped not global"
		 $reference=substr($reference,1);
		 $reference=trim($reference);
		 if ( strlen($reference) === 0 ) return '';
		 $pre='';
		 foreach ( $scopes as $scope ) $pre.=$scope.'.';
 		 $parsed=TemplateParser::FindFinalReference($pre.$reference,$values,$scopes);
		 $result=TemplateParser::GetValue($parsed,$values);
		 if ( is_array($result)
		   && count($result)==2
      	   && $result[0]===TRUE
		   && substr($result[1],0,2) == "!?"
		   && substr($result[1],strlen($result[1])-2-1,2) == '?!'
		   && is(substr($result[1],2,strlen($result[1])-4),$pre.$reference) ) {
		  return NULL;
		 }
		 return $result;
		}
		// Otherwise, we're doing best guess. let's try to find one in the current scope first.
		$pre='';
		foreach ( $scopes as $scope ) $pre.=$scope.'.';
		$parsed=TemplateParser::FindFinalReference($pre.$reference,$values,$scopes);
        $result=TemplateParser::GetValue($parsed,$values);
        if ( is_array($result)
		  && count($result)==2
      	  && $result[0]===TRUE
		  && substr($result[1],0,2) == "!?"
		  && substr($result[1],strlen($result[1])-2-1,2) == '?!'
		  && is(substr($result[1],2,strlen($result[1])-4),$pre.$reference) ) { // We failed to find a scoped variable, fallback to global.
		 $parsed=TemplateParser::FindFinalReference($reference,$values,$scopes);
		 $result=TemplateParser::GetValue($parsed,$values);
		 if ( is_array($result)
		   && count($result)==2
      	   && $result[0]===TRUE
		   && substr($result[1],0,2) == "!?"
		   && substr($result[1],strlen($result[1])-2-1,2) == '?!'
		   && is(substr($result[1],2,strlen($result[1])-4),$pre.$reference) ) { // We failed to find a global variable.
		  return NULL;
		 }
		}		
		return $result;
	}
	
	// Expects $block to be a string like "each products as product" or "each product.owners as owner"
	public static function ParseEachAs($block,&$each,&$as) {
		if ( !contains($block,"as") ) return array(TRUE,"'Each' clause missing 'as'");
		$parts=explode(" as ",$block);
		if ( count($parts) !== 2 ) return array(TRUE,"Malformed 'each ... as ...'");
		$each=$parts[0];
		$as=$parts[1];
		return TRUE;
	}
	
	private function PerformEach( $values,$scopes,$each,$as,$inner ) {
		$collection_reference=$scopes;
		$collection_reference[]=$each;
		$collection_reference=implode('.',$collection_reference);
		$collection = TemplateParser::GetVariable($collection_reference,$values,$scopes);
		if ( !is_array($collection) ) return array(TRUE,"Each block did not find collection",$collection_reference);
		$result='';
		$i=0;
		foreach ( $collection as $item ) {
			$cscopes=$cscopes;
			$cscopes[]=$each;
			$cscopes[]=$i;
			$j=0;
			$this->Parse($j,$inner,$values,$cscopes);
			$i++;
		}
		return $result;
	}
	
	private static function ParseConditionalBlock( $block,$values,$scopes ) {
		$block=str_replace(array("(",")"),array('',''),$block);
		$words=words($block);
		$value = TemplateParser::GetVariable($words[1],$values,$scopes);
		if ( is_null($value) ) return FALSE;
		if ( is_array($value) ) return count($value) > 0;
		if ( matches($value,"yes") ) return TRUE;
		if ( matches($value,"no") ) return FALSE;
		if ( matches($value,"true") ) return TRUE;
		if ( matches($value,"false") ) return FALSE;
		if ( is_decimal($value) ) return floatval($value) > 0.0;
		if ( is_numeric($value) ) return intval($value) > 0;
		if ( !is_null($value) ) return TRUE;
		return FALSE;
	}
	
	private function PerformConditional( $values,$scopes,$block,$inner,$elif,$else ) {
		if ( TemplateParser::ParseConditionalBlock($block,$values,$scopes) ) {
			$i=0;
			return $this->Parse($i,$inner,$values,$scopes);
		}
		foreach ( $elif as $e ) {
			if ( TemplateParser::ParseConditionalBlock($e[0],$values,$scopes) ) {
				$i=0;
				return $this->Parse($i,$e[1],$values,$scopes);
			}
		}
		if ( $else !== FALSE ) {
			if ( TemplateParser::ParseConditionalBlock($else[0],$values,$scopes) ) {
				$i=0;
				return $this->Parse($i,$else[1],$values,$scopes);
			}
		} else return '';
	}
	
	private function _Parse(&$i,$template,$values,$scopes=array()) {
		$len=strlen($template);
	    if ( $len === 0 ) return $template;
		$rendered='';
		while ( $i <= $len ) {
			$pre = TemplateParser::AccumulateToBlockStart($i,$len,$template);
			$block_start = $i;
			$block = TemplateParser::AccumulateToBlockEnd($i,$len,$template);
			$block_type = TemplateParser::QueryBlockType($block);
			switch ( $block_type ) {
				case TEMPLATE_BLOCK_ERROR: // Could be a variable or an error.
				    $pieces = words($block);
					$parsed = '';
					foreach( $pieces as $piece ) {
						$variadic=$this->GetVariable($piece,$values,$scopes);
						if ( is_null($variadic) ) return array(TRUE,"Invalid variable reference",$block_start);
						if ( is_array($variadic) ) $variadic=implode(',',$variadic);
						if ( strlen($parsed) == 0 ) $parsed=$variadic;
						else $parsed.=' '.$variadic;
					}
					$rendered.=$pre.$parsed;
				break; ///////////////////
				case TEMPLATE_BLOCK_EACH: // Find the matching loop end...
					$eachs=0;
					$j=$i;
					$inner='';
					$as=NULL;
					$each=NULL;
					$each_parse_result=TemplateParser::ParseEachAs($block,$each,$as);
					if ( is_array($each_parse_result) ) return array_merge( $each_parse_result, array( $block_start ) );
					$endloop=-1;
					$stop=FALSE;
					while ( $j <= $len && $stop !== TRUE ) {
						$inner .= TemplateParser::AccumulateToBlockStart($j,$len,$template);
						$inner_block_start = $j;
						$inner_block = TemplateParser::AccumulateToBlockEnd($j,$len,$template);
						$inner_block_type = TemplateParser::QueryBlockType($inner_block);
						switch ( $inner_block_type ) {
							case TEMPLATE_BLOCK_EACH: $eaches++; $inner.=TemplateParser::EnTemplaten($inner_block); break;
							case TEMPLATE_BLOCK_ENDLOOP:
							 if ( $eaches > 0 ) {
								 $inner.=TemplateParser::EnTemplaten($inner_block);
								 $eaches--;
							 } else {
								 $endloop=$inner_block_start;
								 $stop=TRUE;
							 }
							break;
							default: $inner.=TemplateParser::EnTemplaten($inner_block); break;
						}
					}
					if ( $stop === FALSE || $endloop === -1 ) {
						return array(TRUE,"Each without matching endloop",$block_start);
					}
					$rendered.=$this->PerformEach( $values,$scopes,$each,$as,$inner );
					$i=$j;
				break; ///////////////////
				case TEMPLATE_BLOCK_IF:  // Find the matching elseifs, else and endif...
					$ifs=0;
					$j=$i;
					$inner='';
					$else=FALSE;
					$elif[]=array();
					$endif=-1;
					$stop=FALSE;
					while ( $j <= $len && $stop !== TRUE ) {
						$inner .= TemplateParser::AccumulateToBlockStart($j,$len,$template);
						$inner_block_start = $j;
						$inner_block = TemplateParser::AccumulateToBlockEnd($j,$len,$template);
						$inner_block_type = TemplateParser::QueryBlockType($inner_block);
						switch ( $inner_block_type ) {
							case TEMPLATE_BLOCK_IF:	$ifs++; $inner.=TemplateParser::EnTemplaten($inner_block); break;
							case TEMPLATE_BLOCK_ELSE:
							 if ( $ifs > 0 ) $inner.=TemplateParser::EnTemplaten($inner_block); 
							 else {
								 $e_ifs=0;
								 $remainder='';
								 while ( $j<= $len ) {
									$remainder .= TemplateParser::AccumulateToBlockStart($j,$len,$template);
									$remainder_block_start = $j;
									$remainder_block = TemplateParser::AccumulateToBlockEnd($j,$len,$template);
									$remainder_block_type = TemplateParser::QueryBlockType($remainder_block);									 
									switch ( $remainder_block_type ) {
										case TEMPLATE_BLOCK_IF:	$e_ifs++; $remainder.=TemplateParser::EnTemplaten($remainder_block); break;
										case TEMPLATE_BLOCK_ELSEIF:
										  if ( $e_ifs > 0 ) $remainder.=TemplateParser::EnTemplaten($remainder_block);
										  else {
										  	return array(TRUE,"Elseif after else before endif",$remainder_block_start);
										  }
										break;
										case TEMPLATE_BLOCK_ENDIF:
										  if ( $e_ifs > 0 ) {
											$remainder.=TemplateParser::EnTemplaten($remainder_block);
											$e_ifs--;
										  } else {
											$else = $remainder;
										    $endif = $remainder_block_start;
										    $stop=TRUE;
										  }
										break;
										default: $remainder.=TemplateParser::EnTemplaten($remainder_block); break;
									}
								 }
								 $else=array($inner_block, $remainder);								 
							 }
							break;
							case TEMPLATE_BLOCK_ELSEIF:
							 if ( $ifs > 0 ) $inner.=TemplateParser::EnTemplaten($inner_block);
							 else {
								 $e_ifs=0;
								 $remainder='';
								 $elif_stop=FALSE;
								 while ( $j<= $len && $elif_stop !== TRUE ) {
									$elif_inner .= TemplateParser::AccumulateToBlockStart($j,$len,$template);
									$elif_inner_block_start = $j;
									$elif_inner_block = TemplateParser::AccumulateToBlockEnd($j,$len,$template);
									$elif_inner_block_type = TemplateParser::QueryBlockType($elif_inner_block);									 
									switch ( $elif_inner_block_type ) {
										case TEMPLATE_BLOCK_IF:	$e_ifs++; $elif_inner.=TemplateParser::EnTemplaten($elif_inner_block); break;
										case TEMPLATE_BLOCK_ELSEIF:
										case TEMPLATE_BLOCK_ELSE:
										  if ( $e_ifs > 0 ) {
											$elif_inner.=TemplateParser::EnTemplaten($elif_inner_block);
										  } else { // We've reached the end of this ELSEIF
										    $j=$elif_inner_block_start;
											$elif[]=array( $inner_block, $elif_inner );
										    $elif_stop=TRUE;
										  }
										break;
										case TEMPLATE_BLOCK_ENDIF:
										  if ( $e_ifs > 0 ) {
											$elif_inner.=TemplateParser::EnTemplaten($elif_inner_block);
											$e_ifs--;
										  } else { // We've reached the end of this ELSEIF
										    $j=$elif_inner_block_start;
											$elif[]=array( $inner_block, $elif_inner );
										    $endif = $inner_block_start;
										    $elif_stop=TRUE;
										  }
										break;
										default: $elif_inner.=TemplateParser::EnTemplaten($elif_inner_block); break;
									}
								 }
							 }
							break;
							case TEMPLATE_BLOCK_ENDIF:
							 if ( $ifs > 0 ) {
								  $inner.=TemplateParser::EnTemplaten($inner_block);
								  $ifs--;
							 } else {
								 $endif=$inner_block_start;
								 $stop=TRUE;
							 }
							break;
							default: $inner.=TemplateParser::EnTemplaten($inner_block); break;
						}
					}
					if ( $stop === FALSE || $endif === -1 ) {
						return array(TRUE,"If without matching endif",$block_start);
					}
					$rendered.=$this->PerformConditional( $values,$scopes,$block,$inner,$elif,$else );
					$i=$j;
				break; ///////////////////
				case TEMPLATE_BLOCK_ENDIF:
					return array(TRUE,"Endif appears before if",$block_start);
				break; ///////////////////
				case TEMPLATE_BLOCK_ENDLOOP:
					return array(TRUE,"End/endloop before each",$block_start);
				break; ///////////////////
				case TEMPLATE_BLOCK_ELSE:
					return array(TRUE,"Else appears before if",$block_start);
				break;
			}
		}
		return $rendered;
	}
			
    public function Parse(&$i,$template,$values,$scopes=array()) {
		return $this->_Parse($i,$template,$values,$scopes);
    }	
};

/**
 * Class RenderTemplate
 */
class Templating
{
	
    public function __construct() {}

    public static function Render( $template, $values ) {
		$i=0;
		$b=new TemplateParser();
		return $b->Parse($i,$template,$values);
	}
}
