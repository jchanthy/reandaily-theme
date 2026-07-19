<?php
/**
 * Template Name: Contact & Payment Page Template
 * 
 * A custom premium Contact Admin and Payment Information page
 * incorporating Cambodians' favorite payment & communication methods (ABA/Telegram).
 */
get_header(); ?>

<div id="wrapper" class="wrapper" style="background-color: #ffffff !important;">

<!-- Premium Hero Banner -->
<section class="archive-banner" style="background: radial-gradient(circle at 10% 90%, rgba(0, 123, 255, 0.04) 0%, rgba(255, 255, 255, 0) 50%), radial-gradient(circle at 90% 10%, rgba(0, 242, 254, 0.04) 0%, rgba(255, 255, 255, 0) 50%), #ffffff; padding: 80px 0; text-align: center; border-bottom: 1px solid #e2e8f0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <span class="banner-badge" style="display: inline-block; background: rgba(0, 123, 255, 0.08); color: #007bff; padding: 6px 16px; border-radius: 50px; font-size: 14px; font-weight: 700; margin-bottom: 20px; font-family: 'Kantumruy Pro', sans-serif !important;">📞 ទំនាក់ទំនង និងការបង់ប្រាក់ (Contact & Payment)</span>
        <h1 style="font-size: 42px; font-weight: 900; color: #0f172a; margin: 0 0 15px 0; letter-spacing: -0.02em; font-family: 'Kantumruy Pro', sans-serif !important; line-height: 1.3;">ព័ត៌មានទំនាក់ទំនង និងការបង់ប្រាក់វគ្គសិក្សា</h1>
        <p style="font-size: 18px; color: #64748b; max-width: 700px; margin: 0 auto; line-height: 1.7; font-family: 'Kantumruy Pro', sans-serif !important;">សូមមើលព័ត៌មានគណនីបង់ប្រាក់ និងវិធីសាស្រ្តទំនាក់ទំនងខាងក្រោម ដើម្បីទទួលបានការធ្វើសកម្មភាពចូលរៀនបានលឿនបំផុត។</p>
    </div>
</section>

<!-- Content Section -->
<section style="padding: 80px 0; background: #ffffff;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 60px; align-items: start;">
        
        <!-- Left Column: Payment Details -->
        <div>
            <h2 style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 25px; font-family: 'Kantumruy Pro', sans-serif !important;">💳 វិធីសាស្ត្របង់ប្រាក់ (Payment Method)</h2>
            
            <!-- ABA Card Mockup -->
            <div style="background: linear-gradient(135deg, #005a9c 0%, #002f6c 100%); color: #ffffff; padding: 35px; border-radius: 20px; box-shadow: 0 15px 30px rgba(0, 90, 156, 0.25); margin-bottom: 30px; position: relative; overflow: hidden;">
                <div style="position: absolute; bottom: -20px; right: -20px; font-size: 120px; opacity: 0.05; font-weight: 900;">ABA</div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                    <span style="font-size: 18px; font-weight: 700; letter-spacing: 0.1em; font-family: sans-serif;">ABA BANK</span>
                    <span style="font-size: 12px; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-family: 'Kantumruy Pro', sans-serif !important;">គណនីវេរប្រាក់</span>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <span style="font-size: 13px; color: rgba(255,255,255,0.7); display: block; margin-bottom: 5px; font-family: 'Kantumruy Pro', sans-serif !important;">ឈ្មោះគណនី (Account Name)</span>
                    <span style="font-size: 22px; font-weight: 800; letter-spacing: 0.05em; font-family: 'Kantumruy Pro', sans-serif !important; text-transform: uppercase;">REANDAILY ACADEMY</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                    <div>
                        <span style="font-size: 13px; color: rgba(255,255,255,0.7); display: block; margin-bottom: 5px; font-family: 'Kantumruy Pro', sans-serif !important;">លេខគណនី (Account Number)</span>
                        <span style="font-size: 26px; font-weight: 850; font-family: sans-serif; letter-spacing: 0.05em;">000 123 456</span>
                    </div>
                    <span style="font-size: 32px;">🏦</span>
                </div>
            </div>
            
            <!-- Instructions List -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 30px;">
                <h4 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 20px 0; font-family: 'Kantumruy Pro', sans-serif !important; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">📝 ជំហាននៃការបង់ប្រាក់ និងចូលរៀន</h4>
                <ol style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 15px;">
                    <li style="display: flex; gap: 12px; align-items: start;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: #007bff; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; font-size: 13px; font-weight: bold; flex-shrink: 0; margin-top: 2px;">១</span>
                        <p style="margin: 0; font-size: 15px; color: #475569; font-family: 'Kantumruy Pro', sans-serif !important; line-height: 1.6;">
                            ផ្ទេរប្រាក់តាមចំនួនតម្លៃវគ្គសិក្សាមកគណនី <strong>ABA Bank</strong> ខាងលើ។
                        </p>
                    </li>
                    <li style="display: flex; gap: 12px; align-items: start;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: #007bff; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; font-size: 13px; font-weight: bold; flex-shrink: 0; margin-top: 2px;">២</span>
                        <p style="margin: 0; font-size: 15px; color: #475569; font-family: 'Kantumruy Pro', sans-serif !important; line-height: 1.6;">
                            រក្សាទុក <strong>វិក្កយបត្រផ្ទេរប្រាក់ជោគជ័យ (Screenshot of Receipt)</strong> ទុកជាភស្តុតាង។
                        </p>
                    </li>
                    <li style="display: flex; gap: 12px; align-items: start;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: #007bff; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; font-size: 13px; font-weight: bold; flex-shrink: 0; margin-top: 2px;">៣</span>
                        <p style="margin: 0; font-size: 15px; color: #475569; font-family: 'Kantumruy Pro', sans-serif !important; line-height: 1.6;">
                            ផ្ញើវិក្កយបត្រនោះរួមជាមួយ <strong>អាសយដ្ឋានអ៊ីមែលចុះឈ្មោះសិក្សា</strong> របស់អ្នកមកកាន់ Telegram របស់ Admin នៅផ្នែកខាងស្តាំ។ គណនីសិក្សារបស់អ្នកនឹងត្រូវបើកជូនភ្លាមៗ!
                        </p>
                    </li>
                </ol>
            </div>
        </div>
        
        <!-- Right Column: Contact Details (Redesigned Profile Card) -->
        <div>
            <h2 style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 25px; font-family: 'Kantumruy Pro', sans-serif !important;">💬 ទំនាក់ទំនង Admin (Contact Admin)</h2>
            
            <div style="display: flex; flex-direction: column; gap: 25px;">
                
                <!-- Admin Bio Card (Meng Hann) -->
                <div style="background: radial-gradient(circle at top right, rgba(0, 123, 255, 0.03) 0%, rgba(255,255,255,0) 60%), #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 35px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03); text-align: center; position: relative; overflow: hidden;">
                    
                    <!-- Avatar Area -->
                    <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 20px auto;">
                        <!-- Outer pulsing soft gradient border -->
                        <div style="position: absolute; inset: -4px; background: linear-gradient(135deg, #007bff 0%, #00f2fe 100%); border-radius: 50%; padding: 4px; box-shadow: 0 8px 20px rgba(0, 123, 255, 0.15);">
                            <div style="background: #ffffff; width: 100%; height: 100%; border-radius: 50%; padding: 3px;">
                                <img src="/wp-content/uploads/2024/03/avatar166335074.jpg" alt="ម៉េង ហាន់" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;" />
                            </div>
                        </div>
                        <!-- Status online indicator badge -->
                        <span style="position: absolute; bottom: 5px; right: 5px; width: 18px; height: 18px; background: #10b981; border: 3px solid #ffffff; border-radius: 50%; z-index: 10;"></span>
                    </div>

                    <!-- Title & Info -->
                    <h3 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 5px 0; font-family: 'Kantumruy Pro', sans-serif !important;">ម៉េង ហាន់ (Meng Hann)</h3>
                    <span style="display: inline-block; font-size: 13px; font-weight: 600; color: #007bff; background: rgba(0, 123, 255, 0.08); padding: 4px 14px; border-radius: 20px; margin-bottom: 20px; font-family: sans-serif; text-transform: uppercase; letter-spacing: 0.05em;">ADMIN & INSTRUCTOR</span>

                    <p style="font-size: 15px; color: #475569; line-height: 1.8; text-align: left; margin: 0 0 25px 0; padding: 20px; background: #f8fafc; border-radius: 16px; border: 1px solid #f1f5f9; font-family: 'Kantumruy Pro', sans-serif !important;">
                        សួស្តីខ្ញុំឈ្មោះ <strong>ម៉េង ហាន់!</strong><br>
                        តើអ្នកចង់ចុះឈ្មោះរៀន ឬអ្នកមានសំណួរអំពីវគ្គសិក្សារបស់យើង ឬត្រូវការជំនួយបច្ចេកទេស? អ្នកអាចទំនាក់ទំនងមកខ្ញុំផ្ទាល់តាមរយៈតេលេក្រាមខាងក្រោម៖
                    </p>

                    <!-- Telegram CTA -->
                    <a href="https://t.me/MengHannKH" target="_blank" class="btn-telegram-pulse" style="background: linear-gradient(135deg, #0088cc 0%, #24a1de 100%) !important; color: #ffffff !important; font-family: 'Kantumruy Pro', sans-serif !important; font-weight: 700; text-decoration: none; padding: 16px 36px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 12px; font-size: 16px; box-shadow: 0 6px 20px rgba(0, 136, 204, 0.25); transition: all 0.3s ease; width: 100%; box-sizing: border-box;">
                        <svg aria-hidden="true" style="width: 20px; height: 20px; fill: currentColor;" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M446.7 98.6l-67.6 318.8c-5.1 22.5-18.4 28.1-37.3 17.5l-103-75.9-49.7 47.8c-5.5 5.5-10.1 10.1-20.7 10.1l7.4-104.9 190.9-172.5c8.3-7.4-1.8-11.5-12.9-4.1L117.8 284 16.2 252.2c-22.1-6.9-22.5-22.1 4.6-32.7L418.2 66.4c18.4-6.9 34.5 4.1 28.5 32.2z"></path></svg>
                        ជជែកតាម Telegram (Chat to Admin)
                    </a>
                </div>

                <!-- Community Platforms Section -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.02);">
                    <h4 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 20px 0; font-family: 'Kantumruy Pro', sans-serif !important; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span>📢</span> សហគមន៍របស់យើង (Our Community)
                    </h4>
                    
                    <p style="font-size: 14px; color: #64748b; margin-top: -10px; margin-bottom: 20px; font-family: 'Kantumruy Pro', sans-serif !important; line-height: 1.6;">
                        ចូលរួមសិក្សាបន្ថែម និងតាមដានរាល់ការចែករំលែកមេរៀនឥតគិតថ្លៃថ្មីៗពី ReanDaily តាមបណ្តាញសង្គមផ្លូវការខាងក្រោម៖
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <!-- YouTube: Rean Computer 101 -->
                        <a href="https://www.youtube.com/@reancomputer101" target="_blank" style="display: flex; align-items: center; justify-content: space-between; text-decoration: none; padding: 15px 20px; border-radius: 12px; background: rgba(239, 68, 68, 0.04); border: 1px solid rgba(239, 68, 68, 0.1); transition: all 0.3s ease;" class="community-link yt-link">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 24px; background: #ef4444; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; color: #ffffff;">📺</span>
                                <div>
                                    <h5 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">Rean Computer 101</h5>
                                    <p style="font-size: 12px; color: #ef4444; margin: 2px 0 0 0; font-weight: 600; font-family: sans-serif;">YouTube Channel</p>
                                </div>
                            </div>
                            <span style="font-size: 14px; font-weight: bold; color: #ef4444; font-family: 'Kantumruy Pro', sans-serif !important;">ចូលមើល ➜</span>
                        </a>

                        <!-- YouTube: Rean Digital KH -->
                        <a href="https://www.youtube.com/@reandigitalkh" target="_blank" style="display: flex; align-items: center; justify-content: space-between; text-decoration: none; padding: 15px 20px; border-radius: 12px; background: rgba(239, 68, 68, 0.04); border: 1px solid rgba(239, 68, 68, 0.1); transition: all 0.3s ease;" class="community-link yt-link">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 24px; background: #ef4444; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; color: #ffffff;">🎬</span>
                                <div>
                                    <h5 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">Rean Digital KH</h5>
                                    <p style="font-size: 12px; color: #ef4444; margin: 2px 0 0 0; font-weight: 600; font-family: sans-serif;">YouTube Channel</p>
                                </div>
                            </div>
                            <span style="font-size: 14px; font-weight: bold; color: #ef4444; font-family: 'Kantumruy Pro', sans-serif !important;">ចូលមើល ➜</span>
                        </a>

                        <!-- Telegram: Rean Computer 101 -->
                        <a href="https://t.me/reancomputer101" target="_blank" style="display: flex; align-items: center; justify-content: space-between; text-decoration: none; padding: 15px 20px; border-radius: 12px; background: rgba(0, 136, 204, 0.04); border: 1px solid rgba(0, 136, 204, 0.1); transition: all 0.3s ease;" class="community-link tg-link">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 24px; background: #0088cc; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; color: #ffffff;">📱</span>
                                <div>
                                    <h5 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">Rean Computer 101</h5>
                                    <p style="font-size: 12px; color: #0088cc; margin: 2px 0 0 0; font-weight: 600; font-family: sans-serif;">Telegram Channel</p>
                                </div>
                            </div>
                            <span style="font-size: 14px; font-weight: bold; color: #0088cc; font-family: 'Kantumruy Pro', sans-serif !important;">ចូលរួម ➜</span>
                        </a>
                    </div>
                </div>

                <!-- Working Hours Card -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 25px; display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 40px; background: rgba(95, 51, 225, 0.1); width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%;">⏱️</span>
                    <div>
                        <h4 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">ម៉ោងធ្វើការ (Working Hours)</h4>
                        <p style="font-size: 14px; color: #475569; margin: 3px 0 0 0; font-family: 'Kantumruy Pro', sans-serif !important;">រៀងរាល់ថ្ងៃ: ៨:០០ ព្រឹក - ៩:០០ យប់</p>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
</section>

</div>

<!-- CSS Hover Overrides -->
<style>
.contact-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05) !important;
    border-color: #007bff !important;
}
@media (max-width: 991px) {
    div[style*="grid-template-columns: 1.15fr 0.85fr"],
    div[style*="grid-template-columns: 1.1fr 0.9fr"] {
        grid-template-columns: 1fr !important;
        gap: 40px !important;
    }
}
</style>

<?php get_footer(); ?>
