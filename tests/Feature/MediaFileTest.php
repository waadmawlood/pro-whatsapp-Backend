<?php

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\MediaFile;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MediaFileTest extends TestCase
{
    public function test_signed_media_url_serves_image_with_content_type(): void
    {
        Storage::fake('public');

        $account = WhatsAppAccount::factory()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $conversation = Conversation::factory()->create([
            'company_id' => $this->company->id,
            'whatsapp_account_id' => $account->id,
            'customer_id' => $customer->id,
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'conversation_id' => $conversation->id,
            'whatsapp_account_id' => $account->id,
            'direction' => MessageDirection::Inbound,
            'type' => MessageType::Image,
            'status' => 'delivered',
        ]);

        $path = 'media/'.$this->company->id.'/'.$message->id.'/photo.jpg';
        Storage::disk('public')->put($path, 'fake-image');

        $media = MediaFile::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'message_id' => $message->id,
            'type' => MessageType::Image,
            'filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'disk' => 'public',
            'path' => $path,
        ]);

        $url = URL::temporarySignedRoute('api.v1.media-files.show', now()->addHour(), [
            'mediaFile' => $media->id,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }
}
