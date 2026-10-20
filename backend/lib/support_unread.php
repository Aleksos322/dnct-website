<?php
declare(strict_types=1);
/** Called only after the endpoint has resolved a ticket owned by the viewer. */
function support_customer_unread(PDO $db, int $ticketId): int
{
    $q=$db->prepare("SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id=:ticket AND m.author_role='admin' AND m.withdrawn_at IS NULL AND m.id>COALESCE((SELECT MAX(r.last_message_id) FROM support_message_receipts r WHERE r.ticket_id=m.ticket_id AND r.participant_role='customer'),0)");
    $q->execute([':ticket'=>$ticketId]);return (int)$q->fetchColumn();
}
