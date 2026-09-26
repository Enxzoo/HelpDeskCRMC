<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CRMC Helpdesk</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

    body {
      font-family: 'Inter', sans-serif;
    }

    .display {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .blue-grad {
      background: linear-gradient(155deg, #2171B5 0%, #1557A0 100%);
    }

    .light-blue-grad {
      background: linear-gradient(135deg, #BDD7E7, #2171B5);
    }

    .blue-glass {
      background: radial-gradient(circle, rgba(107, 174, 214, .25), transparent 70%);
    }

    .cool-bubble {
      background: linear-gradient(135deg, #EFF3FF, #BDD7E7);
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-14px); }
    }

    .floaty {
      animation: float 5s ease-in-out infinite;
    }

    @keyframes fadeup {
      from { opacity: 0; transform: translateY(24px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .fadeup {
      animation: fadeup .7s ease-out both;
    }

    .shadow-card {
      box-shadow: 0 20px 40px rgba(15, 8, 20, .08);
    }

    .shadow-sm2 {
      box-shadow: 0 4px 12px rgba(15, 8, 20, .04);
    }
  </style>
</head>
<body>
  <div class="min-h-screen bg-[#EFF3FF] text-[#1A171E] overflow-x-hidden">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-[#EFF3FF]/95 backdrop-blur border-b border-[#E6E3EB]">
      <div class="max-w-[1440px] mx-auto px-8 lg:px-12 h-[88px] flex items-center justify-between">
        <a id="nav-brand" href="/" class="flex items-center gap-3">
          <img src="assets/images/helpdesk-logo.png" alt="HelpdeskCRMC" class="w-[190px] h-auto">
        </a>
        <nav class="hidden md:flex items-center gap-9 text-sm font-medium text-[#7A7485]">
          <a id="nav-features" href="#features" class="hover:text-[#2171B5] transition-colors">Features</a>
          <a id="nav-benai" href="#benai" class="hover:text-[#2171B5] transition-colors">Meet BenAI</a>
          <a id="nav-how" href="#how" class="hover:text-[#2171B5] transition-colors">How it works</a>
          <a id="nav-faq" href="#faq" class="hover:text-[#2171B5] transition-colors">FAQ</a>
        </nav>
        <div class="flex items-center gap-3">
          <a id="nav-login" href="login.php" class="text-sm font-semibold text-[#2171B5] px-4 py-2 rounded-full hover:bg-white transition-colors">Log in</a>
          <a id="nav-signup" href="login.php" class="text-sm font-semibold text-white blue-grad px-5 py-2.5 rounded-full shadow-sm2 hover:opacity-95 transition-opacity">Get Started</a>
        </div>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative max-w-[1440px] mx-auto px-8 lg:px-12 pt-24 pb-32 flex flex-col lg:flex-row gap-20 items-center">
      <div class="blue-glass absolute -top-10 -left-16 w-80 h-80 rounded-full pointer-events-none"></div>
      <div class="relative fadeup flex-1">

        <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#2171B5] bg-white px-4 py-2 rounded-full mb-6 border border-[#BDD7E7]">
          <iconify-icon icon="lucide:sparkles" class="text-[#6BAED6]"></iconify-icon>
          AI-Powered Student Support
        </span>
        <h1 class="display font-extrabold text-[60px] lg:text-[72px] leading-[1.05] tracking-tight mb-8">
          Help is always<br>on at <span class="text-[#2171B5]">CRMC.</span>
        </h1>
        <p class="text-xl text-[#7A7485] leading-relaxed max-w-xl mb-10">
          Meet BenAI — your friendly campus assistant. Get instant answers on enrollment, grades, tuition, schedules, and more. No queues, no waiting. Just help, 24/7.
        </p>
        <div class="flex flex-wrap items-center gap-5 mb-12">
          <a id="hero-start" href="login.php" class="inline-flex items-center gap-2 text-white blue-grad px-8 py-4 rounded-full font-semibold shadow-card text-lg hover:opacity-95 transition-opacity">
            Ask BenAI now <iconify-icon icon="lucide:arrow-right"></iconify-icon>
          </a>
          <a id="hero-tour" href="#how" class="inline-flex items-center gap-2 text-[#1A171E] bg-white border border-[#E6E3EB] px-8 py-4 rounded-full font-semibold hover:border-[#2171B5] text-lg transition-colors">
            <iconify-icon icon="lucide:play-circle" class="text-[#2171B5]"></iconify-icon>
            Take a tour
          </a>
        </div>
        <div class="flex items-center gap-6">
          <div class="flex -space-x-3">
            <span class="w-9 h-9 rounded-full border-2 border-white bg-[#2171B5]"></span>
            <span class="w-9 h-9 rounded-full border-2 border-white bg-[#6BAED6]"></span>
            <span class="w-9 h-9 rounded-full border-2 border-white bg-[#BDD7E7]"></span>
            <span class="w-9 h-9 rounded-full border-2 border-white bg-[#2F8F5B]"></span>
          </div>
          <p class="text-sm text-[#7A7485]">
            <span class="font-bold text-[#1A171E]">4,200+</span> students helped this semester
          </p>
        </div>
      </div>

      <div class="relative fadeup flex-1 flex justify-center lg:justify-end" style="animation-delay:.15s">
        <div class="relative floaty">
          <div class="blue-glass absolute inset-0 scale-125 pointer-events-none"></div>
          <div class="relative bg-white rounded-[32px] shadow-card border border-[#ECEAF0] p-9 max-w-[500px] mx-auto">
            <div class="flex items-center gap-3 pb-4 border-b border-[#ECEAF0]">
              <div class="w-16 h-16 rounded-full overflow-hidden bg-[#EFF3FF] flex items-center justify-center">
                <img src="assets/images/ben_interactions vector/hi_bot.png" alt="BenAI" class="w-full h-full object-cover">
              </div>
              <div>
                <p class="display font-bold text-sm">BenAI</p>
                <p class="text-xs text-[#2F8F5B] flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-[#2F8F5B]"></span>
                  Online now
                </p>
              </div>
            </div>
            <div class="space-y-3 py-5">
              <div class="cool-bubble rounded-2xl rounded-tl-md px-4 py-3 text-sm max-w-[85%]">Hi! I'm BenAI 👋 How can I help you today?</div>
              <div class="blue-grad text-white rounded-2xl rounded-tr-md px-4 py-3 text-sm max-w-[80%] ml-auto">When is the enrollment deadline?</div>
              <div class="cool-bubble rounded-2xl rounded-tl-md px-4 py-3 text-sm max-w-[88%]">Enrollment for 2nd sem closes on <b>Nov 15</b>. Want me to open the enrollment portal for you?</div>
            </div>
            <div class="flex items-center gap-2 bg-[#F5F4F7] rounded-full px-4 py-2.5">
              <input placeholder="Ask a question..." class="bg-transparent flex-1 text-sm outline-none placeholder:text-[#8A8593]">
              <button class="w-9 h-9 rounded-full blue-grad text-white flex items-center justify-center">
                <iconify-icon icon="lucide:send"></iconify-icon>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <section class="bg-white border-y border-[#ECEAF0] py-8">
      <div class="max-w-[1240px] mx-auto px-8 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
        <div>
          <p class="display font-extrabold text-3xl text-[#2171B5]">24/7</p>
          <p class="text-sm text-[#7A7485] mt-1">Always available</p>
        </div>
        <div>
          <p class="display font-extrabold text-3xl text-[#2171B5]">&lt;5s</p>
          <p class="text-sm text-[#7A7485] mt-1">Avg. response time</p>
        </div>
        <div>
          <p class="display font-extrabold text-3xl text-[#2171B5]">12k+</p>
          <p class="text-sm text-[#7A7485] mt-1">Questions answered</p>
        </div>
        <div>
          <p class="display font-extrabold text-3xl text-[#2171B5]">96%</p>
          <p class="text-sm text-[#7A7485] mt-1">Resolved instantly</p>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="max-w-[1440px] mx-auto px-8 lg:px-12 py-32">
      <div class="text-center max-w-2xl mx-auto mb-14">
        <span class="text-xs font-semibold text-[#6BAED6] uppercase tracking-wider">Features</span>
        <h2 class="display font-extrabold text-5xl tracking-tight mt-4 mb-6">Everything you need, in one place</h2>
        <p class="text-[#7A7485] text-lg">From admission questions to grade concerns — CRMC Helpdesk connects you to the right answer, fast.</p>
      </div>
      <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white rounded-[24px] p-7 border border-[#ECEAF0] shadow-sm2 hover:shadow-card transition-shadow">
          <span class="w-12 h-12 rounded-2xl bg-[#EFF3FF] text-[#2171B5] flex items-center justify-center text-2xl mb-5">
            <iconify-icon icon="lucide:message-circle-heart"></iconify-icon>
          </span>
          <h3 class="display font-bold text-lg mb-2">Instant AI Answers</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">BenAI understands your questions in plain language and replies instantly — day or night.</p>
        </div>
        <div class="bg-white rounded-[24px] p-7 border border-[#ECEAF0] shadow-sm2 hover:shadow-card transition-shadow">
          <span class="w-12 h-12 rounded-2xl bg-[#EAF2F8] text-[#6BAED6] flex items-center justify-center text-2xl mb-5">
            <iconify-icon icon="lucide:ticket"></iconify-icon>
          </span>
          <h3 class="display font-bold text-lg mb-2">Smart Ticketing</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Complex concern? BenAI routes it to the right office and tracks it until it's resolved.</p>
        </div>
        <div class="bg-white rounded-[24px] p-7 border border-[#ECEAF0] shadow-sm2 hover:shadow-card transition-shadow">
          <span class="w-12 h-12 rounded-2xl bg-[#E7F3EC] text-[#2F8F5B] flex items-center justify-center text-2xl mb-5">
            <iconify-icon icon="lucide:calendar-check"></iconify-icon>
          </span>
          <h3 class="display font-bold text-lg mb-2">Schedules & Deadlines</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Never miss enrollment, exams, or payment dates — get reminders that matter to you.</p>
        </div>
        <div class="bg-white rounded-[24px] p-7 border border-[#ECEAF0] shadow-sm2 hover:shadow-card transition-shadow">
          <span class="w-12 h-12 rounded-2xl bg-[#EFF3FF] text-[#2171B5] flex items-center justify-center text-2xl mb-5">
            <iconify-icon icon="lucide:file-text"></iconify-icon>
          </span>
          <h3 class="display font-bold text-lg mb-2">Records & Requests</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Request documents, check grades, and view your account balance in a few taps.</p>
        </div>
        <div class="bg-white rounded-[24px] p-7 border border-[#ECEAF0] shadow-sm2 hover:shadow-card transition-shadow">
          <span class="w-12 h-12 rounded-2xl bg-[#EAF2F8] text-[#6BAED6] flex items-center justify-center text-2xl mb-5">
            <iconify-icon icon="lucide:shield-check"></iconify-icon>
          </span>
          <h3 class="display font-bold text-lg mb-2">Private & Secure</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Your data stays protected. Only you and authorized staff can access your records.</p>
        </div>
        <div class="bg-white rounded-[24px] p-7 border border-[#ECEAF0] shadow-sm2 hover:shadow-card transition-shadow">
          <span class="w-12 h-12 rounded-2xl bg-[#EFF3FF] text-[#2171B5] flex items-center justify-center text-2xl mb-5">
            <iconify-icon icon="lucide:languages"></iconify-icon>
          </span>
          <h3 class="display font-bold text-lg mb-2">Speaks Your Language</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Ask in English, Filipino, or Bisaya — BenAI meets you where you're comfortable.</p>
        </div>
      </div>
    </section>

    <!-- BenAI Section -->
    <section id="benai" class="blue-grad relative overflow-hidden">
      <div class="blue-glass absolute top-0 right-0 w-96 h-96 pointer-events-none"></div>
      <div class="max-w-[1440px] mx-auto px-8 py-8 grid lg:grid-cols-2 gap-16 items-center relative">
        <!-- Left: Ben in rounded card -->
        <div class="flex justify-center lg:justify-start">
          <div class="bg-white rounded-[56px] shadow-card p-6" style="display: flex; align-items: center; justify-content: center;">
            <img src="assets/images/ben_interactions vector/hi_bot.png" alt="BenAI greeting" style="width: 400px; height: auto; display: block; transform: translateX(30px);">
          </div>
        </div>
        <!-- Right: Text content -->
        <div class="text-white">
          <span class="text-xs font-semibold text-[#BDD7E7] uppercase tracking-wider">Meet your assistant</span>
          <h2 class="display font-extrabold text-4xl lg:text-5xl tracking-tight mt-3 mb-5">Say hello to BenAI</h2>
          <p class="text-white/90 text-lg leading-relaxed mb-8">
            BenAI is CRMC's warm, ever-ready AI companion — glasses on, blue uniform pressed, and always ready to help. Think of BenAI as the friend who knows every office, every deadline, and every answer.
          </p>
          <ul class="space-y-4">
            <li class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-white text-sm">✓</span>
              <span class="text-white/95">Friendly, human-like conversations</span>
            </li>
            <li class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-white text-sm">✓</span>
              <span class="text-white/95">Knows CRMC policies inside out</span>
            </li>
            <li class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-white text-sm">✓</span>
              <span class="text-white/95">Hands off to real staff when needed</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="how" class="max-w-[1240px] mx-auto px-8 py-24">
      <div class="text-center max-w-2xl mx-auto mb-14">
        <span class="text-xs font-semibold text-[#6BAED6] uppercase tracking-wider">How it works</span>
        <h2 class="display font-extrabold text-4xl tracking-tight mt-3 mb-4">Get help in three simple steps</h2>
      </div>
      <div class="grid md:grid-cols-3 gap-8">
        <div class="text-center">
          <div class="w-16 h-16 mx-auto rounded-full blue-grad text-white display font-extrabold text-2xl flex items-center justify-center mb-5">1</div>
          <h3 class="display font-bold text-lg mb-2">Log in with your CRMC ID</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Use your student credentials to securely access the helpdesk.</p>
        </div>
        <div class="text-center">
          <div class="w-16 h-16 mx-auto rounded-full blue-grad text-white display font-extrabold text-2xl flex items-center justify-center mb-5">2</div>
          <h3 class="display font-bold text-lg mb-2">Ask BenAI anything</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Type your question in plain words and get an instant, accurate reply.</p>
        </div>
        <div class="text-center">
          <div class="w-16 h-16 mx-auto rounded-full blue-grad text-white display font-extrabold text-2xl flex items-center justify-center mb-5">3</div>
          <h3 class="display font-bold text-lg mb-2">Get it resolved</h3>
          <p class="text-[#7A7485] text-sm leading-relaxed">Solved instantly or handed to the right office — either way, you're covered.</p>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="bg-white border-y border-[#ECEAF0] py-24">
      <div class="max-w-[820px] mx-auto px-8">
        <h2 class="display font-extrabold text-4xl tracking-tight text-center mb-12">Frequently asked questions</h2>
        <div class="space-y-4">
          <details class="group bg-[#EFF3FF] rounded-2xl p-6 cursor-pointer" open>
            <summary class="flex items-center justify-between font-semibold list-none">
              Do I need an account to use CRMC Helpdesk?
              <iconify-icon icon="lucide:chevron-down" class="text-[#2171B5] group-open:rotate-180 transition-transform"></iconify-icon>
            </summary>
            <p class="text-[#7A7485] text-sm mt-3 leading-relaxed">Yes — simply log in with your official CRMC student ID and password to access personalized help and your records.</p>
          </details>
          <details class="group bg-[#EFF3FF] rounded-2xl p-6 cursor-pointer">
            <summary class="flex items-center justify-between font-semibold list-none">
              Is BenAI available 24/7?
              <iconify-icon icon="lucide:chevron-down" class="text-[#2171B5] group-open:rotate-180 transition-transform"></iconify-icon>
            </summary>
            <p class="text-[#7A7485] text-sm mt-3 leading-relaxed">Absolutely. BenAI never sleeps — you can ask questions anytime, including weekends and holidays.</p>
          </details>
          <details class="group bg-[#EFF3FF] rounded-2xl p-6 cursor-pointer">
            <summary class="flex items-center justify-between font-semibold list-none">
              Can I talk to a real person?
              <iconify-icon icon="lucide:chevron-down" class="text-[#2171B5] group-open:rotate-180 transition-transform"></iconify-icon>
            </summary>
            <p class="text-[#7A7485] text-sm mt-3 leading-relaxed">Of course. When a concern needs human attention, BenAI creates a ticket and routes it to the proper CRMC office.</p>
          </details>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="max-w-[1240px] mx-auto px-8 py-24">
      <div class="blue-grad rounded-[48px] px-10 py-20 text-center text-white relative overflow-hidden">
        <div class="blue-glass absolute -bottom-16 -left-16 w-80 h-80 pointer-events-none"></div>
        <div class="relative">
          <!-- Ben centered at top -->
          <div class="flex justify-center mb-8">
            <img src="assets/images/ben_interactions vector/happybot.png" alt="Happy BenAI" class="w-[300px] md:w-[380px] h-auto object-contain floaty">
          </div>
          <!-- Text content below Ben -->
          <h2 class="display font-extrabold text-4xl lg:text-5xl tracking-tight mb-4">Ready to get the help you need?</h2>
          <p class="text-white/90 text-lg mb-10 max-w-2xl mx-auto">Join thousands of CRMC students already getting instant answers with BenAI.</p>
          <a id="cta-final" href="login.php" class="inline-flex items-center gap-2 bg-white text-[#2171B5] px-8 py-4 rounded-full font-semibold text-lg shadow-card hover:opacity-95 transition-opacity">
            Start chatting with BenAI <iconify-icon icon="lucide:arrow-right"></iconify-icon>
          </a>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#1A171E] text-white/70 py-14">
      <div class="max-w-[1240px] mx-auto px-8 grid md:grid-cols-4 gap-10">
        <div>
          <div class="flex items-center gap-3 mb-4 text-white font-bold text-xl">
            HelpdeskCRMC
          </div>
          <p class="text-sm">The AI-powered student helpdesk of Cebu Roosevelt Memorial Colleges.</p>
        </div>
        <div>
          <h4 class="text-white font-semibold mb-4 text-sm">Product</h4>
          <ul class="space-y-2 text-sm">
            <li><a id="f-features" href="#features" class="hover:text-[#6BAED6]">Features</a></li>
            <li><a id="f-benai" href="#benai" class="hover:text-[#6BAED6]">Meet BenAI</a></li>
            <li><a id="f-how" href="#how" class="hover:text-[#6BAED6]">How it works</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-semibold mb-4 text-sm">Support</h4>
          <ul class="space-y-2 text-sm">
            <li><a id="f-faq" href="#faq" class="hover:text-[#6BAED6]">FAQ</a></li>
            <li><a id="f-contact" href="#" class="hover:text-[#6BAED6]">Contact office</a></li>
            <li><a id="f-help" href="#" class="hover:text-[#6BAED6]">Help center</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-semibold mb-4 text-sm">College</h4>
          <ul class="space-y-2 text-sm">
            <li><a id="f-about" href="#" class="hover:text-[#6BAED6]">About CRMC</a></li>
            <li><a id="f-privacy" href="#" class="hover:text-[#6BAED6]">Privacy policy</a></li>
            <li><a id="f-terms" href="#" class="hover:text-[#6BAED6]">Terms of use</a></li>
          </ul>
        </div>
      </div>
      <div class="max-w-[1240px] mx-auto px-8 mt-10 pt-6 border-t border-white/10 text-sm flex flex-col md:flex-row justify-between gap-3">
        <p>© 2024 Cebu Roosevelt Memorial Colleges. All rights reserved.</p>
        <p>Made with ❤️ for CRMC students</p>
      </div>
    </footer>
  </div>
</body>
</html>