<?php
header('Content-Type: application/json; charset=utf-8');

function search_with_brave($query, $num_sources = 9) {    
    // Set your Brave search API key here (https://brave.com/search/api/)
    $BRAVE_KEY = "[fill me in]";

    $params = array('q' => $query, 'text_decorations' => false);
    $ENDPOINT = "https://api.search.brave.com/res/v1/web/search";
    $url = $ENDPOINT . '?' . http_build_query($params);
    $headers = array(
        'X-Subscription-Token: ' . $BRAVE_KEY,
        'Accept: application/json'
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);
    curl_close($curl);

    $jsonContent = json_decode(strip_tags($response), true);
    $snippets = [];
    if (isset($jsonContent['web']['results'])) {
        foreach ($jsonContent['web']['results'] as $c) {
            $snippets[] = ['name' => $c['title'], 'url' => $c['url'], 'snippet' => $c['description']];
        }
    }
    return array_slice($snippets, 0, $num_sources);
}

function setup_curl_to_llm($query, $context, $max_tokens, $model = "gpt-3.5-turbo", $temperature = 1) {
    // Put your OpenAI API key here (https://platform.openai.com/overview)
    // if you want to use other LLMs, most use the exact same API as OpenAI,
    //   so really only the url, model, and KEY need to change 
    $OPENAI_KEY = "[fill me in]";
    $LLM_ENDPOINT = "https://api.openai.com/v1/chat/completions";
    
    $system = (object) ["role" => "system", "content" => $context];
    $user = (object) ["role" => "user", "content" => $query];
    $request = array(
        "model" => $model,
        "messages" => array(
            $system,
            $user
        ),
        "temperature" => $temperature,
        "max_tokens" => $max_tokens
    );
    $headers = array(
        "Content-Type: application/json",
        "Authorization: Bearer " . $OPENAI_KEY,
    );
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request, JSON_PRETTY_PRINT));
    curl_setopt($curl, CURLOPT_URL, $LLM_ENDPOINT);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    return $curl;
}

function execute_curl($curl) {
    $result = curl_exec($curl);
    curl_close($curl);
    $jsonResult = json_decode($result);
    return $jsonResult->choices[0]->message->content;
}

function get_snippets_for_prompt($snippets) {
    $snippets_context = "";
    foreach ($snippets as $i => $s) {
        $snippets_context .= "[citation:" . ($i + 1) . "] " . $s['snippet'] . "\n\n";
    }
    return $snippets_context;
}

function setup_get_answer_prompt($snippets) {
    $starting_context = <<<'EOD'
    You are an assistant written by Josh Clemm. You will be given a question. And you will respond with two things.
    First, respond with an answer to the question. It must be accurate, high-quality, and expertly written in a positive, interesting, and engaging manner. It must be informative and in the same language as the user question.
    Second, respond with 3 related followup questions. First print "==== RELATED ====" verbatim. Then, write the 3 follow up questions in a JSON array format, so it's clear you've started to answer the second part. Do not use markdown. Each related question should be no longer than 15 words. They should be based on user's original question and the citations given in the context. Do not repeat the original question. Make sure to determine the main subject from the user's original question. That subject needs to be in any related question, so the user can ask it standalone.
    For both the first and second response, you will be provided a set of citations for the question. Each will start with a reference number like [citation:x], where x is a number. Always use the related citations and cite the citation at the end of each sentence in the format [citation:x]. If a sentence comes from multiple citations, please list all applicable citations, like [citation:2][citation:3].
    Here are the provided citations:
    EOD;
    return $starting_context . "\n\n" . get_snippets_for_prompt($snippets);;
}

// 0. Extract query and model from request paramaters
$query = $_REQUEST["q"] ?? "how did Uber scale over the years?";
$model = $_REQUEST["model"] ?? "gpt-3.5-turbo";

// 1. Call search to get sources and their snippets
$snippets = search_with_brave($query);
echo "==== SOURCES ====\n" . json_encode($snippets, JSON_PRETTY_PRINT);

// 2. Create a prompt passing along the sources and call the language model of your choice
$answer_prompt_context = setup_get_answer_prompt($snippets);
echo $answer_prompt_context;
$answer_curl = setup_curl_to_llm($query, $answer_prompt_context, 2048, $model, 0.9);
echo "\n==== ANSWER ====\n" . execute_curl($answer_curl);
?>