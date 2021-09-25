## laravel Samples 2021

Here are some Laravel coding samples to show you how I work. Its not runnable as files not connected to each other but it gives you general idea about my coding style and depth.

Ive placed the files in appropriate directories based on `laravel 7x` (but only those dirs that I needed for the samples and not all the dirs of laravel setup are here), therefore please check all dirs for example files. The coding is in `php7x`.

Lastly, Ive left notes as comments in each file to let you know whats going on (as much as I could).

## Example work in this sample:

1) <b>app\Http\Controllers\lorem\helloWorldOpr.php</b>\
The example controller ive got here is a form processor that does `Add`, `Edit`, `Clone` as well as uses a 3rd party vendor to `generate pdf` and then also `emails the pdf as an attachment`. This ive done to show you the deep extend of things we can do with a form in laravel with much ease.

2) <b>app\Console\Kernel.php</b>\
This shows how I run various schedules and jobs behind the scene without user interaction.

3) <b>app\Jobs\TestJob1.php</b>\
An example of a Job that is pulls data from a 3rd party API and after some processing saves that data into an sql database.

4) <b>app\Mail\testMailClass.php</b>\
Mail object used to customize emails and assign templates.

5) <b>app\Models\Temp_val.php</b>\
Example of a Model that caches results for optimized performance.

6) <b>resources\views\lorem\helloWorldOpr.blade.php</b>\
Example of a view using blade and js.

7) <b>routes\web.php</b>\
Route file, see how I pass additional params to each route, I catch them in the parent controller via `$request->route()->getAction()`.

8) <b>app\Http\Abstracts, app\Http\Helpers, app\Models</b>\
These folders are not in laravel itself, but ive created them to better oprganize my code.

9) Naming conventions:\
Generally I do a lot more efforts in naming conventions but in this sample I just wanted to do bit more of the logic so I might have skipped it

Let me know if you need even more samples for more fun and tricky things I do in laravel.

## Commenting:

Generally I use the following convention:
1. `#/` code block heading related to few line(s) of code after
2. `//` small hint about the code on the left to this
3. `//` also, to comment out few lines of code
4. `/* */` to define a whole block (class, method etc) with a formal descriptor
5. `/* */` also, to comment out a whole block of code

In this sample, ive done extra extra commenting to tell you whats going on.

## Spacing:

Spacing, indentation and commenting are very important to me

## Design pattern for CRUD Operation:

https://en.wikipedia.org/wiki/Post/Redirect/Get

This is most important thing to me when it comes to any form processing, so I always implement this.