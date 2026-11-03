<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central AI Brain and agent workforce (additive).
 *
 * ai_agents / ai_agent_versions  registry and versioned definitions
 * ai_tasks                       every task (parent/root for delegation), status, result, usage
 * ai_tool_calls                  audit of every tool invocation
 * ai_approvals                   human approval queue for approval-required actions
 * ai_memories                    controlled memory (task, conversation, agent, shared)
 * ai_knowledge_documents         approved knowledge base
 * ai_usage_records               provider usage per call (tokens; cost only when pricing is configured)
 * ai_drafts                      draft content prepared by agents (never published without approval)
 * support_tickets                support cases escalated to staff
 * staff_tasks                    internal tasks for staff (including those created from approvals)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->string('role', 160);
            $table->text('description');
            $table->text('instructions');
            $table->string('model_tier', 20)->default('routine'); // routine | extraction | support | research | reasoning
            $table->string('model', 80)->nullable();               // override; null = tier default
            $table->decimal('temperature', 3, 2)->default(0.2);
            $table->json('tools');
            $table->json('memory_policy');
            $table->string('status', 24)->default('active');       // active | disabled | maintenance | error
            $table->string('stage', 1);                            // A-E rollout stage
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('daily_token_budget')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_agent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('change_note', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['agent_id', 'version']);
        });

        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->unsignedBigInteger('root_id')->nullable()->index();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 16)->default('admin');     // admin | website | whatsapp | schedule | event | agent
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->string('title', 255);
            $table->text('request');
            $table->json('input')->nullable();
            $table->string('intent', 60)->nullable();
            $table->string('status', 20)->default('pending');   // pending | running | awaiting_approval | completed | failed | cancelled
            $table->string('priority', 10)->default('normal');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->string('mode', 10)->nullable();             // rules | llm
            $table->unsignedSmallInteger('tool_calls')->default(0);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_usd', 10, 6)->nullable();     // null = pricing not configured
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['agent_id', 'created_at']);
        });

        Schema::create('ai_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('ai_tasks')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('tool', 60);
            $table->string('risk', 20);
            $table->json('input')->nullable();
            $table->string('status', 20);                       // ok | denied | invalid | failed | approval_requested | prohibited
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['tool', 'status']);
        });

        Schema::create('ai_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('action', 60);
            $table->string('risk', 20);
            $table->text('description');
            $table->json('payload');
            $table->json('evidence')->nullable();
            $table->string('status', 12)->default('pending');   // pending | approved | rejected | expired
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->json('execution')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->cascadeOnDelete(); // null = shared business knowledge
            $table->string('scope', 16);                        // task | conversation | agent | shared
            $table->string('reference', 120)->nullable();       // task uuid / conversation id
            $table->string('key', 120);
            $table->text('content');
            $table->string('source', 255);
            $table->string('status', 12)->default('approved');  // proposed | approved | archived
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['scope', 'agent_id', 'status']);
            $table->index(['scope', 'reference']);
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->string('title', 200);
            $table->text('body');
            $table->string('source', 255);
            $table->json('agents')->nullable();                 // null = every agent
            $table->string('status', 12)->default('draft');     // draft | approved | archived
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'category']);
        });

        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('provider', 30);
            $table->string('model', 80);
            $table->string('purpose', 30);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->boolean('ok')->default(true);
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['agent_id', 'created_at']);
        });

        Schema::create('ai_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('type', 30);                         // marketing | social | seo_brief | email | proposal
            $table->string('channel', 30)->nullable();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 12)->default('draft');     // draft | approved | rejected | published
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('category', 30);
            $table->string('subject', 200);
            $table->text('summary');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 12)->default('open');      // open | in_progress | resolved
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description');
            $table->string('category', 30);
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 12)->default('open');      // open | done | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('approval_id')->nullable()->constrained('ai_approvals')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_on']);
        });
    }

    public function down(): void
    {
        foreach (['staff_tasks', 'support_tickets', 'ai_drafts', 'ai_usage_records', 'ai_knowledge_documents', 'ai_memories', 'ai_approvals', 'ai_tool_calls', 'ai_tasks', 'ai_agent_versions', 'ai_agents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
