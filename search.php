<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

$start = microtime(true);

$query = $_REQUEST["q"] ?? "who is the author of this";
$model = $_REQUEST["model"] ?? "gpt-3.5-turbo";
if (!in_array($model, array('gpt-3.5-turbo', 'gpt-4', 'mixtral-8x7b', 'llama2-70b'), true)) {
    $model = "gpt-3.5-turbo";
}

if ($model == "mixtral-8x7b") {
    $model = "mixtral-8x7b-32768";
}
else if ($model == "llama2-70b") {
    $model = "llama2-70b-4096";
}

function search_with_brave($query, $num_sources = 9)
{    
    // Put your Brave search API key here (https://brave.com/search/api/)
    $BRAVE_KEY = "[fill me in]";

    $curl = curl_init();

    $params = array(
        'q' => $query
    );
    $ENDPOINT = "https://api.search.brave.com/res/v1/web/search";
    $url = $ENDPOINT . '?' . http_build_query($params);

    $headers = array(
        'X-Subscription-Token: ' . $BRAVE_KEY,
        'Accept: application/json'
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($curl);
    curl_close($curl);

    $jsonContent = json_decode($response, true);

    $snippets = [];

    if (isset($jsonContent['web']['results'])) {
        foreach ($jsonContent['web']['results'] as $c) {

            $extra_snippets = "";
            if (isset($c['extra_snippets'])) {
                foreach ($c['extra_snippets'] as $s) {
                    $extra_snippets .= $s . ' ';
                }
            }
            $snippets[] = [
                'name' => $c['title'],
                'url' => $c['url'],
                'snippet' => $c['description'],
                'extra_snippets' => $extra_snippets ?? '',
                'favicon' => $c['meta_url']['favicon'] ?? '',
            ];
        }
    }

    return array_slice($snippets, 0, $num_sources);
}
function search_with_serper($query, $num_sources = 9)
{
    // Put your google search serper key here (https://serper.dev/)
    $SERPER_KEY = "[fill me in]"; 

    $curl = curl_init();

    $request = array(
        "q" => $query
    );
    $data = json_encode($request, JSON_PRETTY_PRINT);

    $headers = array(
        'X-API-KEY: ' . $SERPER_KEY,
        'Content-Type: application/json'
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_URL, "https://google.serper.dev/search");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($curl);
    curl_close($curl);

    $jsonContent = json_decode($response, true);

    $snippets = [];

    if (isset($jsonContent['knowledgeGraph'])) {
        $url = $jsonContent['knowledgeGraph']['descriptionUrl'] ?? $jsonContent['knowledgeGraph']['website'] ?? null;
        $snippet = $jsonContent['knowledgeGraph']['description'] ?? null;
        if ($url && $snippet) {
            $snippets[] = [
                'name' => $jsonContent['knowledgeGraph']['title'] ?? '',
                'url' => $url,
                'snippet' => $snippet,
            ];
        }
    }

    if (isset($jsonContent['answerBox'])) {
        $url = $jsonContent['answerBox']['link'] ?? $jsonContent['answerBox']['url'] ?? null;
        $snippet = $jsonContent['answerBox']['snippet'] ?? $jsonContent['answerBox']['answer'] ?? null;
        if ($url && $snippet) {
            $snippets[] = [
                'name' => $jsonContent['answerBox']['title'] ?? '',
                'url' => $url,
                'snippet' => $snippet,
            ];
        }
    }

    if (isset($jsonContent['organic'])) {
        foreach ($jsonContent['organic'] as $c) {
            $snippets[] = [
                'name' => $c['title'],
                'url' => $c['link'],
                'snippet' => $c['snippet'] ?? '',
            ];
        }
    }

    return array_slice($snippets, 0, $num_sources);
}

function setup_curl_to_llm($query, $context, $max_tokens, $stream = false, $model = "gpt-3.5-turbo", $temperature = 1)
{
    // Put your OpenAI API key here (https://platform.openai.com/overview)
    // if you want to use other LLMs, most use the exact same API as OpenAI,
    //   so really only the url, model, and KEY need to change 
    $OPENAI_KEY = "[fill me in]";

    // For Groq's API, get your key here (https://wow.groq.com/)
    $GROQ_KEY = "[fill me in]";

    if (in_array($model, array('gpt-3.5-turbo', 'gpt-4'), true)) {
        $LLM_ENDPOINT = "https://api.openai.com/v1/chat/completions";
        $LLM_KEY = $OPENAI_KEY;
    }
    else {
        $LLM_ENDPOINT = "https://api.groq.com/openai/v1/chat/completions";
        $LLM_KEY = $GROQ_KEY;
    }
    
    $system = (object) [
        "role" => "system",
        "content" => $context
    ];

    $user = (object) [
        "role" => "user",
        "content" => $query
    ];

    $request = array(
        "model" => $model,
        "messages" => array(
            $system,
            $user
        ),
        "temperature" => $temperature,
        "stream" => $stream,
        "max_tokens" => $max_tokens
    );
    $data = json_encode($request, JSON_PRETTY_PRINT);

    $curl = curl_init();
    $headers = array(
        "Content-Type: application/json",
        "Authorization: Bearer " . $LLM_KEY,
    );
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    curl_setopt($curl, CURLOPT_URL, $LLM_ENDPOINT);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    if ($stream) {
        //stream back curl chunks, this is messy I know...
        $callback = function ($ch, $str) {
            //$str has the chunks of data streamed back.
            $chunks = explode("data: ", $str);
            foreach ($chunks as $i => $chunk) {
                if (!empty($chunk) || $chunk !== "[DONE]") {
                    $json = json_decode($chunk);
                    if (isset($json->choices)) {
                        $choice = $json->choices[0];
                        if (isset($choice->delta)) {
                            $delta = $choice->delta;
                            if (isset($delta->content)) {
                                echo $delta->content;
                                flush();
                                ob_flush();
                            } else {
                                return -1;
                            }
                        }
                    }
                }
            }
            return strlen($str); //signals curl to keep going
        };
        
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, $callback);
    }

    return $curl;
}

function execute_curl($curl)
{
    $result = curl_exec($curl);
    curl_close($curl);
    $jsonResult = json_decode($result);
    return nl2br($jsonResult->choices[0]->message->content);
}

function get_snippets_for_prompt($snippets)
{
    $snippets_context = "";
    foreach ($snippets as $i => $s) {
        $snippets_context .= "[citation:" . ($i + 1) . "] " . $s['snippet'];

        if(isset($s['extra_snippets'])) {
            $snippets_context .= $s['extra_snippets'];
        }

        if ($i < count($snippets) - 1) {
            $snippets_context .= "\n\n";
        }
    }

    return $snippets_context;
}

function setup_get_answer_prompt($snippets)
{
    // My prompt is to provide accurate, high-quality, and expertly written responses to your questions in a positive, interesting, and engaging manner. I aim to offer informative, logical, and actionable information in the same language as your queries.
    $starting_context = <<<'EOD'
    You are an assistant written by Josh Clemm. You will be given a question. And you will respond with two things.

    First, respond with an answer to the question. It must be accurate, high-quality, and expertly written in a positive, interesting, and engaging manner. It must be informative and in the same language as the user question.
    
    Second, respond with 3 related followup questions. First, please repeat the following phrase: ==== RELATED ====. And then the 3 follow up questions in a JSON array format, so it's clear you've started to answer the second part. Each related question should be no longer than 15 words. They should be based on user's original question and the citations given in the context. Do not repeat the original question. Make sure to determine the main subject from the user's original question. That subject needs to be in any related question, so the user can ask it standalone.

    For both the first and second response, you will be provided a set of citations to the question. Each will start with a reference number like [citation:x], where x is a number. Always use the related citations and cite the citation at the end of each sentence in the format [citation:x]. If a sentence comes from multiple citations, please list all applicable citations, like [citation:2][citation:3].
    
    Here are the provided citations:

    EOD;

    // $final_context = "Finally, don't repeat the provided contexts verbatim. And don't mention you were passed contexts in the response.";
    $final_context = "";

    $full_context = $starting_context . "\n\n" . get_snippets_for_prompt($snippets) . "\n\n" . $final_context;
    return $full_context;
}

// Use the multi cURL capabilities to run one or more curl commands in parallel
function execute_multi_curl(...$curlArray)
{
    $mh = curl_multi_init();
    foreach ($curlArray as $curl) {
        curl_multi_add_handle($mh, $curl);
    }
    // Execute all queries simultaneously, and continue when all are complete
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        // usleep(50000);
        curl_multi_select($mh); // This is a blocking call, only proceeding when there's activity
    } while ($running);

    // Collect the responses and remove the handles
    $responses = [];
    foreach ($curlArray as $curl) {
        $responses[] = curl_multi_getcontent($curl);
        curl_multi_remove_handle($mh, $curl);
    }
    curl_multi_close($mh);
    return $responses;
}

$snippets = array();
// $snippets = search_with_brave($query);
$snippets = search_with_serper($query);

echo "==== SOURCES ====\n";
echo json_encode($snippets, JSON_PRETTY_PRINT);

$search_end = microtime(true);

$answer_prompt_context = setup_get_answer_prompt($snippets);

$answer_curl = setup_curl_to_llm($query, $answer_prompt_context, 2048, true, $model, 0.9);

echo "\n==== ANSWER ====\n";
$responses = execute_multi_curl($answer_curl);

$end = microtime(true);

echo "\n==== METADATA ====\n";
$metadata = array(
    "query" => $query,
    "model" => $model,
    "duration" => array(
        "search" => number_format(($search_end - $start), 2) . 's',
        "llm" => number_format(($end - $search_end), 2) . 's',
        "total" => number_format(($end - $start), 2) . 's'
    )
);
echo json_encode($metadata, JSON_PRETTY_PRINT);
