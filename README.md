# laravel Samples 2021

Here are some Laravel coding samples to show you how I work. Its not runnable as files not connected to each other but it gives you general idea about my coding style and depth.

Ive placed the files in appropriate directories based on laravel 7x (but only those dirs that I needed for the samples and not all the dirs of laravel setup are here), therefore please check all dirs for example files. The coding is in php7x.

Lastly, Ive left notes as comments in each file to let you know whats going on (as much as I could).

# Example work in this sample:

The example controller ive got here does `Add`, `Edit`, `Clone` as well as uses a 3rd party vendor to `generate pdf` and then also `emails the pdf as attachment`. This ive done to show you the deep extend of things we can do with a form in laravel with much ease.

# Commenting:

Generally I use the following convention:
1. `#/` to place information or section heading related to few line(s) of code after
2. `//` small hint about the code on the left to this
3. `//` to comment out few lines of code to delete later or debug
4. `/* */` to define a whole block (class, method etc) as a formal descriptor
5. `/* */` To comment out a whole block of code

In this sample, ive done extra extra commenting to tell you whats going on.

# Spacing:

Spacing, indentation and commenting are very important to me

# Design pattern for CRUD Operation:

https://en.wikipedia.org/wiki/Post/Redirect/Get

This is most important thing to me when it comes to any form processing, so I always implement this.