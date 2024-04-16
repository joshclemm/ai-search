# ai-search
A Basic open source AI search engine, modeled after [Perplexity.ai](Perplexity.ai). If you’re not familiar with an AI-powered question-answering platform, they use a large language model like ChatGPT to answer your questions, but improves on ChatGPT in that it pulls in accurate and real-time search results to supplement the answer (so no “knowledge cutoff”). And lists citations within the answer itself which builds confidence it’s not hallucinating and allows you to research topics further.

## How to Run summary
1. Clone / download repo
2. Go get your API keys and add them to `search.php` (look for "[Fill me in]")
3. Run locally using php (php -S localhost:8000)

## Step by step details

### Step 1: Get Search Results for a user query

The main challenge with LLMs like ChatGPT is that they have knowledge cutoffs (and they occasionally tend to [hallucinate](https://en.wikipedia.org/wiki/Hallucination_(artificial_intelligence))). It’s because they’re trained on data up to a specific date (eg Sep 2021). So if you want an answer to an up-to-date question or you simply want to research a topic in detail, you’ll need to _augment_ the answer with relevant sources. This technique is known as [RAG](https://learn.microsoft.com/en-us/azure/search/retrieval-augmented-generation-overview) (retrieval augmented generation). And in our case we can simply supply the LLM up-to-date information from search engines like Google or Bing.

To build this yourself, you’ll want to first sign up for an API key from [Bing](https://www.microsoft.com/en-us/bing/apis/bing-web-search-api), Google (via [Serper](https://serper.dev/)), [Brave](https://brave.com/search/api/), or others. Bing, Brave, and Serper all offer free usage to get started.

In `search.php`, put your API key where appropriate (look for "[Fill me in]"). For this example, I'm have code for both Brave and Google via Serper.


### Step 2: Decide the LLM you want to use

Here, you’ll need to sign up for an API key from an LLM provider. There’s a lot of providers to choose from right now. For example there’s [OpenAI](https://platform.openai.com/docs/overview), [Anthropic](https://www.anthropic.com/api), [Anyscale](https://www.anyscale.com/), [Groq](https://groq.com/), [Cloudflare](https://ai.cloudflare.com/), [Perplexity](https://docs.perplexity.ai/docs/getting-started), [Lepton](https://www.lepton.ai/), or the big players like AWS, Azure, or Google Cloud. I’ve used many of these with success and they offer a subset of current and popular closed and open source models. And each model has unique strengths, different costs, and different speeds. For example, gpt-4 is very accurate but expensive and slow. When in doubt, I’d recommend using chatgpt-3.5-turbo from OpenAI. It’s good enough, cheap enough, and fast enough to test this out.

Fortunately, most of these LLM serving providers are compatible with OpenAI’s API format, so switching to another provider / model is only minimal work (or just ask a [chatbot](https://yaddleai.com/search/?q=Show+the+code+to+call+openAI%27s+API) to write the code!).

In `search.php`, put your API keys where appropriate (look for "[Fill me in]"). For this example, I'm using OpenAI (for chatgpt-3.5-turbo / gpt-4) and Groq (for Mixtral-8b7b). So to keep your work minimal, just go get keys for one or both of those.

### Step 3: Craft a prompt to pass along the search results in the context window

When you want to ask an LLM a question, you can provide a lot of additional context. Each model has its own unique limit and some of them are very large. For [gpt-4-turbo](https://platform.openai.com/docs/models/continuous-model-upgrades), you could pass along the entirety of the [1st Harry Potter book](https://towardsdatascience.com/de-coded-understanding-context-windows-for-transformer-models-cd1baca6427e) with your question. Google’s super powerful [Gemini 1.5](https://medium.com/google-cloud/googles-gemini-1-5-pro-revolutionizing-ai-with-a-1m-token-context-window-bfea5adfd35f) can support a context size of over a million tokens. That’s enough to pass along the entirety of the 7-book Harry Potter series!

Fortunately, passing along the snippets of 8-10 search results is far smaller, allowing you to use many of the faster (and much cheaper) models like gpt-3.5-turbo or mistral-7b.

In my experience, passing along the user question, custom prompt message, and search result snippets are usually under 1K tokens. This is well under even the most basic model’s limits so this should be no problem.

`search.php` has the sample prompt I’ve been playing around with you. Hat-tip to the folks at [Lepton AI](https://www.lepton.ai/) who [open-sourced a similar project](https://github.com/leptonai/search_with_lepton) which helped me refine this prompt.

### Step 4: Add Related or Follow Up Questions

One of the nice features of Perplexity is how they suggest follow up questions. Fortunately, this is easy to replicate.

To do this, you can make a second call to your LLM (in parallel) asking for related questions. And don’t forget to pass along those citations in the context again.

Or, you can attempt to construct a prompt so that the LLM answers the question AND comes up with related questions. This saves an API call and some tokens, but it’s a bit challenging getting these LLMs to always answer in a consistent and repeatable format.

### Step 5: Make it look a lot better!

To make this a complete example, we need a usable UI. I kept the UI as simple as possible and everything is in `index.html`. I’m using Bootstrap, jquery, and some basic CSS / javascript, markdown, and a JS syntax highlighter to make this happen.

To improve the experience, the UI does the following:
* The answer **streams** back to the user (improving perception of speed)
* The **citations** are replaced by a nicer in-line UI with a clickable popup for the user to learn more
* The **sources** considered are included after the answer in case the user wants to explore further
* Markdown and code syntax **highlighting** are used if necessary

### Working Example

To explore a working example, check out [https://yaddleai.com](https://yaddleai.com/). It's mostly the same code though I added a second search call in parallel to fetch images, I wrote a separate page to fetch the latest news, and a few other minor improvements.
