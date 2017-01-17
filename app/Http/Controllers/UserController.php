<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use App\User;
use App\Stock;
use App\Watchlist;
use Auth;

class UserController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index()
    {
        //
    }

    /**
    * Show the form for creating a new resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function create()
    {
        //
    }

    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function store(Request $request)
    {
        // Check if all data are present
        $username = $request->get('username');
        $fullname = $request->get('fullname');
        $password = $request->get('password');

        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users|alpha_dash|min:6',
            'fullname' => 'required',
            'password' => 'required',
        ]);

        if($validator->fails()){
            return response()->json([
                'error' => $validator->errors()->all(),
                'status' => 'FAILED'
            ]);
        }

        $user = User::create([
            'username' => $username,
            'fullname' => $fullname,
            'password' => bcrypt($password),
        ]);
        return response()->json([
            'error' => [],
            'status' => 'OK',
            'data' => $user
        ]);


    }

    /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show($id)
    {
        //
    }

    /**
    * Show the form for editing the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function edit($id)
    {
        //
    }

    /**
    * Update the specified resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function update(Request $request, $id)
    {
        //
    }

    /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function destroy($id)
    {
        //
    }



    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function getWatchlist()
    {

        $watchlist = Watchlist::select('stock_symbol as symbol')->where([
            'user_id' => Auth::id(),
            ])->get();

            foreach ($watchlist as $key => $stock) {

                $stockdata = \App::call('\App\Http\Controllers\StockController@show', [ 'symbol' => $stock['symbol'], 'options' => 'n,s,a,b,o,p,v,c1,p2,g,h,s6,k,j,j5,k4,j6,k5,j1,x,f6,j2,a5,b6,k3,a2,e' ]);

                $stockdata = Stock::where([
                    'symbol' => $stock['symbol']
                ]
                )->first();

                if(isset($stockdata['profile'])) $stockdata['profile'] = json_decode($stockdata['profile']);
                if(isset($stockdata['statistics'])) $stockdata['statistics'] = json_decode($stockdata['statistics']);

                $watchlist[$key]['data'] = $stockdata ?: [];

                if(empty($watchlist[$key]['data'])) unset($watchlist[$key]);
            }

            return response()->json([
                'error' => [],
                'status' => 'OK',
                'watchlist' => $watchlist ?: []
            ]
        );
    }


    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function addToWatchlist($symbol)
    {

        Watchlist::firstOrCreate([
            'user_id' => Auth::id(),
            'stock_symbol' => $symbol
        ]);

        return response()->json([
            'error' => [],
            'status' => 'OK'
        ]);
    }

    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function removeFromWatchlist($symbol)
    {

        Watchlist::where([
            'user_id' => Auth::id(),
            'stock_symbol' => $symbol
        ])
        ->delete();

        return response()->json([
            'error' => [],
            'status' => 'OK'
        ]);
    }

    public function isUsernameAvailable(Request $request){

        $username = $request->get('username');

        if(empty($username)){
            return response()->json([
                'error' => ['Empty Username'],
                'status' => 'failed',
            ]);
        }

        $status = 'taken';

        if(User::where('username', $username)->count()>0){
            return response()->json([
                'error' => ['Username already taken'],
                'status' => 'taken',
            ]);
        }else{
            return response()->json([
                'error' => ['Username available'],
                'status' => 'available',
            ]);
        }

    }

    public function authenticate(Request $request){
        if (Auth::attempt(['username' => $request->get('username'), 'password' => $request->get('password')])) {
            // Authentication passed...

            $user = User::select('username','fullname')->where(['username' => $request->get('username')])->first();

            return response()->json([
                'error' => [],
                'status' => 'OK',
                'user' => $user
            ]);
        }

        return response()->json([
            'error' => ['Username or Password incorrect'],
            'status' => 'FAILED',
        ]);
    }

    public function logout(){
        Auth::logout();

        return response()->json([
            'error' => [],
            'status' => 'OK',
        ]);
    }

    public function isLoggedIn(){
        if (Auth::check()) {
            return response()->json([
                'error' => [],
                'status' => 'OK',
                'user' => Auth::user()
            ]);
        }

        return response()->json([
            'error' => ['No user logged in.'],
            'status' => 'FAILED',
        ]);
    }

    public function getTransactions(){
        return response()->json([
            'data' => User::find(Auth::id())->transactions()->latest()->get()->map(function ($item, $key) {
                $item->type = strtoupper($item->type);
                $item->priceFormatted = '$' . number_format($item->price,2);
                $item->total = $item->price * $item->qty;
                $item->totalFormatted = '$' . number_format($item->total,2);
                $item->idFormatted = str_pad($item->id, 6, "0", STR_PAD_LEFT);
                $item->purchasedTimeAgo = $item->updated_at->diffForHumans();
                return $item;
            }),
            'status' => 'OK'
        ]);
    }

    public function portfolio(){

        $startingMoney = 100000;
        $totalShares = 0;
        $totalGains = 0;

        $transactions = User::find(Auth::id())->transactions()->get()->map(function ($item, $key) {
            $item->type = strtoupper($item->type);
            $item->priceFormatted = '$' . number_format($item->price,2);

            $item->total = $item->price * $item->qty;
            $item->totalFormatted = '$' . number_format($item->total,2);

            if($item->type=='SELL') $item->totalFormatted = '(' . $item->totalFormatted . ')';

            $item->idFormatted = str_pad($item->id, 6, "0", STR_PAD_LEFT);
            $item->purchasedTimeAgo = $item->updated_at->diffForHumans();
            return $item;
        });
        $stocks = [];
        foreach ($transactions as $key => $entry) {
            if(!isset($stocks[$entry->symbol]))
            $stocks[$entry->symbol] = [
                'symbol' => $entry->symbol,
                'qty' => 0,
                'purchasedPriceTotal' => 0,
                'history' => [],
                'gain' => 0
            ];


            if(!isset($stocks[$entry->symbol]['statistics'])){
                $stockData = Stock::select('statistics','name')->where('symbol',$entry->symbol)->first();
                // details.statistics.financialData.currentPrice
                $stocks[$entry->symbol]['statistics'] = json_decode($stockData->statistics);
            }

            if($entry->type==='BUY'){
                $stocks[$entry->symbol]['qty'] += $entry->qty;
                $totalShares += $entry->qty;
                $stocks[$entry->symbol]['purchasedPriceTotal'] += ($entry->price * $entry->qty);

                $stocks[$entry->symbol]['gain'] += (($stocks[$entry->symbol]['statistics']->financialData->currentPrice->raw * $entry->qty)  - ($entry->price * $entry->qty) );
                $totalGains += $stocks[$entry->symbol]['gain'];
            }
            else{
                // $stocks[$entry->symbol]['qty'] -= $entry->qty;
                // $totalShares -= $entry->qty;

                // $stocks[$entry->symbol]['gain'] += ($entry->price * $entry->qty);
                // $totalGains += $stocks[$entry->symbol]['gain'];
            }


            $stocks[$entry->symbol]['history'][] = $entry;

        }

        foreach ($stocks as $key => $stock) {

            $stock['name'] = $stockData->name;
            $stock['currentPrice'] = number_format($stock['statistics']->financialData->currentPrice->raw,2);
            $stock['purchasedPriceTotal'] = number_format($stock['purchasedPriceTotal'],2);
            $stock['currentPriceTotal'] = number_format($stock['qty'] * $stock['statistics']->financialData->currentPrice->raw,2);

            // Total Gain/Loss
            // $stock['gain'] = $stock['currentPriceTotal'] - $stock['purchasedPriceTotal'];
            // $totalGains += $stock['gain'];
            $stock['gain'] = number_format($stock['gain'],2);

            $stock['gainPercent'] = $stock['gain']!=0 ? number_format(($stock['gain'] / $stock['purchasedPriceTotal']) * 100,2) : '0.00';
            $stock['history'] = array_reverse($stock['history']);

            $stocks[$key] = $stock;

        }

        return response()->json([
            'stocks' => $stocks,
            'portfolio' => [
                'totalShares' => $totalShares,
                'totalCompanies' => count($stocks),
                'startingMoney' => $startingMoney,
                'totalGains' => $totalGains,
                'totalGainsFmt' => '$' . number_format($totalGains,2),
                'totalGainsPercent' => ($totalGains!=0 ? number_format(($totalGains / $startingMoney) * 100,2) : '0.00'),
                'accountValue' => $startingMoney - $totalGains,
                'accountValueFmt' => '$' . number_format($startingMoney - $totalGains,2),
            ]
        ]);
    }

}
