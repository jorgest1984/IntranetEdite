<?php
require_once('fpdf.php');

class PDF_Curve extends FPDF {
    function Curve($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3, $style='') {
        $op = $this->_getDrawStyle($style);
        $this->_out(sprintf('%.2F %.2F m', $x0*$this->k, ($this->h-$y0)*$this->k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1*$this->k, ($this->h-$y1)*$this->k, $x2*$this->k, ($this->h-$y2)*$this->k, $x3*$this->k, ($this->h-$y3)*$this->k));
        $this->_out($op);
    }
    
    // Draw a custom shape composed of straight lines and curves
    // $points is an array of operations:
    // array( 'type' => 'm'/'l'/'c', 'x' => ..., 'y' => ..., ('x1', 'y1', 'x2', 'y2') for 'c' )
    function DrawShape($points, $style='F') {
        foreach($points as $pt) {
            if ($pt['type'] == 'm') {
                $this->_out(sprintf('%.2F %.2F m', $pt['x']*$this->k, ($this->h - $pt['y'])*$this->k));
            } elseif ($pt['type'] == 'l') {
                $this->_out(sprintf('%.2F %.2F l', $pt['x']*$this->k, ($this->h - $pt['y'])*$this->k));
            } elseif ($pt['type'] == 'c') {
                $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', 
                    $pt['x1']*$this->k, ($this->h - $pt['y1'])*$this->k, 
                    $pt['x2']*$this->k, ($this->h - $pt['y2'])*$this->k, 
                    $pt['x']*$this->k, ($this->h - $pt['y'])*$this->k));
            }
        }
        $op = $this->_getDrawStyle($style);
        $this->_out($op);
    }

    function _getDrawStyle($style) {
        if($style=='F')
            return 'f';
        elseif($style=='FD' || $style=='DF')
            return 'b';
        else
            return 'S';
    }
}
?>
