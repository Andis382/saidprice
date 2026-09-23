<?php

// Messages sent over WhatsApp. :placeholders are filled by Messenger::send().
return [
    'quote_sent' => "Hello :name, here is your written quote :number from :business.\n\n:title\nTotal: :total\nProposed: :when\nValid until: :valid_until\n\nSee the details and accept or decline here:\n:link\n\nOr just reply 1 to accept, 2 to decline.",
    'quote_nudge' => ":text\n:link",
    'quote_accepted_notice' => "Accepted: :customer said yes to :number (:title, :total). Proposed: :when.\n:link",
    'quote_declined_notice' => "Declined: :customer said no to :number (:title, :total). Reason: :reason\n:link",
    'quote_question_notice' => "Question from :customer about :number (:title):\n\":question\"\nReply to them: :reply\n:link",
    'quote_thanks' => 'Thank you, :name! :business has your acceptance of quote :number (:total) and will be in touch to confirm :when.',
    'quote_draft_created' => "Draft :number is ready from :source. Check it and send it here:\n:link",
];
