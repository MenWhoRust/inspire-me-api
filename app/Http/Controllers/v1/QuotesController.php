<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Services\v1\QuotesService;

use App\Quote;

class QuotesController extends Controller
{
    protected $quotesService;
    protected $rules = [
        'quote_content' => 'required',
        'quotee_id' => 'required|exists:quotees,id',
        'category_id' => 'required|exists:categories,id',
        'keywords' => 'nullable',

    ];

    public function __construct(QuotesService $service)
    {
        $this->quotesService = $service;
        $this->middleware('auth:api', ['except' => ['index', 'show']]);
    }

    public function index()
    {
        $parameters = array_change_key_case(request()->input());
        $quotes = $this->quotesService->getQuotes($parameters);

        if (empty($quotes)) {
            return response('No Quotes found with the given criteria', 404);
        }

        return response()->json($quotes);
    }

    public function show($id)
    {
        $parameters = array_change_key_case(request()->input());
        $parameters['id'] = $id;
        $quotes = $this->quotesService->getQuotes($parameters);
        return response()->json($quotes);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->input(), $this->rules);
        if ($validation->fails()) {
            return response()->json($validation->messages(), 400);
        }

        $quote = new Quote;

        $quote->quote_content = $request->input('quote_content');
        $quote->quotee_id = $request->input('quotee_id');
        $quote->category_id = $request->input('category_id');
        $quote->keywords = $request->input('keywords');

        $quote->save();

        return response()->json($quote, 201);
    }


    public function update(Request $request, $id)
    {
        $validation = Validator::make($request->input(), $this->rules);
        if ($validation->fails()) {
            return response()->json($validation->messages(), 400);
        }

        $quote = Quote::where('id', $id)->firstOrFail();


        $quote->quote_content = $request->input('quote_content');
        $quote->quotee_id = $request->input('quotee_id');
        $quote->category_id = $request->input('category_id');
        $quote->keywords = $request->input('keywords');

        $quote->save();

        return response()->json($quote, 200);
    }

    public function destroy($id)
    {
        Quote::where('id', $id)->firstOrFail()->delete();
        $data = [
            'message' => 'Record successfully deleted'
        ];
        return response()->json($data, 200);
    }
}
