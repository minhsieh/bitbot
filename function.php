<?php

//無條件進位
function ceil_dec($v, $precision){
    $c = pow(10, $precision);
    return ceil($v*$c)/$c;
}
//無條件捨去
function floor_dec($v, $precision){
    $c = pow(10, $precision);
    return floor($v*$c)/$c;
}