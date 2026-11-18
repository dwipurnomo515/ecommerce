<?php

return [

    /*
    | Zetago's commission on the ITEM price of each order (shipping is not
    | commissioned: it pays the courier). In basis points: 500 = 5,00 %.
    | Basis points keep it an integer (no float money math) and allow
    | rates like 2,5 % (250).
    |
    | The rate in force is copied onto each order when it is placed, so
    | changing this later never changes what past orders paid.
    */
    'commission_bps' => (int) env('MARKETPLACE_COMMISSION_BPS', 500),

];
