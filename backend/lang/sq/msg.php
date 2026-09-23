<?php

// Mesazhet që dërgohen në WhatsApp. :placeholders plotësohen nga Messenger::send().
return [
    'quote_sent' => "Përshëndetje :name, ja oferta juaj me shkrim :number nga :business.\n\n:title\nTotali: :total\nData e propozuar: :when\nE vlefshme deri më: :valid_until\n\nShihni detajet dhe pranojeni ose refuzojeni këtu:\n:link\n\nOse thjesht përgjigjuni 1 për ta pranuar, 2 për ta refuzuar.",
    'quote_nudge' => ":text\n:link",
    'quote_accepted_notice' => "Pranuar: :customer tha po për :number (:title, :total). Data e propozuar: :when.\n:link",
    'quote_declined_notice' => "Refuzuar: :customer tha jo për :number (:title, :total). Arsyeja: :reason\n:link",
    'quote_question_notice' => "Pyetje nga :customer për :number (:title):\n\":question\"\nPërgjigjuni: :reply\n:link",
    'quote_thanks' => 'Faleminderit, :name! :business e mori pranimin tuaj për ofertën :number (:total) dhe do t’ju kontaktojë për të konfirmuar :when.',
    'quote_draft_created' => "Drafti :number u bë gati nga :source. Kontrollojeni dhe dërgojeni këtu:\n:link",
];
