# ပရိုဂရမ်မင်း သင်တန်းဇုံ

PHP + MySQL ဖြင့် တည်ဆောက်ထားသော Programming Course Platform တစ်ခုဖြစ်ပြီး စာအုပ်ဒေါင်းလုဒ်, သင်တန်းတက်ရောက်မှု စီမံခန့်ခွဲမှု, ဗီဒီယိုသင်ခန်းစာကြည့်ခြင်း၊ Favourite စာအုပ်များသိုလှောင်ခြင်း၊ Chat Community နှင့် အက်ဒ်မင်ထိန်းချုပ်မှုများကို မြန်မာဘာသာဖြင့် ဝန်ဆောင်မှုပေးပါသည်။

## အဓိက လုပ်ဆောင်ချက်များ

- သင်တန်းစာရင်းကြည့်ရှုပြီး သင်တန်းတက်ရန် လျှောက်ထားနိုင်ခြင်း
- အခမဲ့ ဗီဒီယိုသင်ခန်းစာ ၂ ခုကြည့်ရှုနိုင်ပြီး အောင်မြင်စွာ စာရင်းသွင်းလျှင် မည်သည့်သင်ခန်းစာမဆို ကြည့်ခြင်း/ဒေါင်းလုဒ်လုပ်နိုင်ခြင်း
- Programming Language စာအုပ်များ ဒေါင်းလုဒ် + Favourite ထည့်ခြင်း
- ဆရာများ၏ ပရိုဖိုင်ကြည့်ရန်၊ Like ပေးရန်
- သင်တန်းဝင်များအတွက် Course-Level Chat Community
- အက်ဒ်မင်များအတွက် သင်တန်းတက်လိုသူများကို အတည်ပြု/ပယ်ချနိုင်ခြင်း၊ ဆရာဝင်ငွေ ထိန်းချုပ်နိုင်ခြင်း၊ Trainee Learning Insights များကြည့်ရူနိုင်ခြင်း

## Setup လုပ်နည်း

1. `database/schema.sql` ကို MySQL တွင် run လိုက်ပါ။
2. `config.php` ထဲတွင် DB ချိတ်ဆက်ဖို့ Credential များကို ကိုက်ညီစွာပြင်ပါ။
3. XAMPP (သို့) PHP Server တင်ထားသော Directory ထဲသို့ Project ကို ထည့်ပါ။
4. ပရိုဂျက်ကို Root မဟုတ်ဘဲ Sub-folder တစ်ခု (ဥပမာ `/ProgrammingCoursesProj`) ထဲတွင် တင်ထားလျှင် `.htaccess` မလိုပဲ `.env` သို့မဟုတ် Server Config ထဲတွင်  
   `APP_BASE_PATH=/ProgrammingCoursesProj` သတ်မှတ်ပေးပါ (`config.php` မှ တန်ဖိုးယူသည်)။

### Sample Credentials

| နာမည် | Email | Password | Role |
|-------|-------|----------|------|
| အက်ဒ်မင် | `admin@codehub.local` | `secret123` | admin |
| ဆရာ မင်းထက် | `mentor@codehub.local` | `teachme` | instructor |
| ကျောင်းသား ကိုကို | `learner@codehub.local` | `learn123` | student |

## ဖိုင်ဖွဲ့စည်းမှု

- `index.php` – မူလ Landing Page
- `courses.php`, `course.php`, `watch_lesson.php` – သင်တန်းကြည့်ရှုရန်
- `books.php` – စာအုပ်ဒေါင်းလုဒ်နှင့် Favourite
- `dashboard.php` – သင်တန်းဝင် မျက်နှာစာ
- `chat.php` – Course Chat
- `admin.php` – အက်ဒ်မင် ထိန်းချုပ်မှု
- `actions/` – Form/Post Handler များ
- `database/schema.sql` – DB Schema + Seed Data
- `storage/books`, `storage/videos` – Demo ဖိုင်များ

## အရေးကြီးသတိပေးချက်များ

- Production သို့ တင်မီ `storage/` ဖိုင်များကို တကယ့် PDF/Video များဖြင့် အစားထိုးပါ။
- CSRF token များကို Action Link + Form များတွင် ထည့်သွင်းထားပြီး Session ထဲတွင် စီမံထားပါသည်။
- Chat Feature သို့ ဝင်ရန် သင်တန်းကို Admin က အတည်ပြုထားရပါမည်။

## အဆင့်မြှင့်ရန် အကြံပြုချက်များ

1. Lesson Streaming အတွက် Real Video CDN သို့မဟုတ် Signed URL ထည့်သွင်းခြင်း
2. Payment Gateway တပ်ဆင်၍ သင်တန်းကျသင့်ငွေကို အလိုအလျောက် အတည်ပြုနိုင်စေခြင်း
3. Notification (Email/Web Push) တွင် လျှောက်ထားပြီးမြန်မည့် အခြေအနေများကို အသိပေးခြင်း
