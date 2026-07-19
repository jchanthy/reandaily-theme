<?php
/**
 * Template Name: About Us Page Template
 * 
 * A premium custom-coded About Us page featuring high-fidelity grids, 
 * clean slate-and-white aesthetics, and bold Kantumruy Pro typography.
 */
get_header(); ?>

<div id="wrapper" class="wrapper" style="background-color: #ffffff !important;">

<!-- Premium Hero Banner -->
<section class="archive-banner" style="background: radial-gradient(circle at 10% 90%, rgba(0, 123, 255, 0.04) 0%, rgba(255, 255, 255, 0) 50%), radial-gradient(circle at 90% 10%, rgba(0, 242, 254, 0.04) 0%, rgba(255, 255, 255, 0) 50%), #ffffff; padding: 80px 0; text-align: center; border-bottom: 1px solid #e2e8f0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <span class="banner-badge" style="display: inline-block; background: rgba(0, 123, 255, 0.08); color: #007bff; padding: 6px 16px; border-radius: 50px; font-size: 14px; font-weight: 700; margin-bottom: 20px; font-family: 'Kantumruy Pro', sans-serif !important;">📚 អំពីយើង (About ReanDaily)</span>
        <h1 style="font-size: 42px; font-weight: 900; color: #0f172a; margin: 0 0 15px 0; letter-spacing: -0.02em; font-family: 'Kantumruy Pro', sans-serif !important; line-height: 1.3;">បេសកកម្មសម្រាប់ការរៀនសូត្រឥតឈប់ឈរ</h1>
        <p style="font-size: 18px; color: #64748b; max-width: 700px; margin: 0 auto; line-height: 1.7; font-family: 'Kantumruy Pro', sans-serif !important;">យើងជឿជាក់ថា ការអប់រំ និងការអភិវឌ្ឍសមត្ថភាពជាប្រចាំ គឺជាគន្លឹះដ៏សំខាន់ក្នុងការកសាងអនាគតដ៏ភ្លឺស្វាងសម្រាប់បុគ្គលគ្រប់រូប និងសង្គមជាតិទាំងមូល។</p>
    </div>
</section>

<!-- Our Story Section -->
<section style="padding: 80px 0; background: #ffffff;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;">
        
        <!-- Story Text -->
        <div>
            <h2 style="font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 20px; font-family: 'Kantumruy Pro', sans-serif !important;">តើ ReanDaily គឺជាអ្វី?</h2>
            <p style="font-size: 16px; color: #475569; line-height: 1.8; margin-bottom: 20px; font-family: 'Kantumruy Pro', sans-serif !important;">
                <strong>ReanDaily</strong> គឺជាវេទិកាសិក្សាអនឡាញឈានមុខគេ ដែលត្រូវបានបង្កើតឡើងក្នុងគោលបំណងផ្តល់ជូននូវវគ្គសិក្សាជំនាញជាក់ស្តែងដែលមានគុណភាពខ្ពស់ និងសម្បូរបែប សម្រាប់សិស្ស និស្សិត និងអ្នកជំនាញគ្រប់រូបក្នុងប្រទេសកម្ពុជា។
            </p>
            <p style="font-size: 16px; color: #475569; line-height: 1.8; margin-bottom: 20px; font-family: 'Kantumruy Pro', sans-serif !important;">
                យើងបានសហការយ៉ាងយកចិត្តទុកដាក់ជាមួយគ្រូឧទ្ទេស និងអ្នកជំនាញដែលមានបទពិសោធន៍ខ្ពស់ក្នុងវិស័យនីមួយៗ ដើម្បីរៀបចំវគ្គសិក្សាដែលឆ្លើយតបទៅនឹងតម្រូវការទីផ្សារការងារជាក់ស្តែង ទាំងជំនាញបច្ចេកវិទ្យា ទីផ្សារ ឌីហ្សាញ និងភាសាបរទេស។
            </p>
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="btn btn-primary" style="font-family: 'Kantumruy Pro', sans-serif !important; font-weight: 700; text-decoration: none; padding: 14px 28px; border-radius: 8px;">ចាប់ផ្តើមរៀនឥឡូវនេះ</a>
                <a href="#values" class="btn btn-secondary" style="font-family: 'Kantumruy Pro', sans-serif !important; font-weight: 700; text-decoration: none; padding: 12px 26px; border-radius: 8px; border: 2px solid #007bff; color: #007bff;">ស្វែងយល់បន្ថែម</a>
            </div>
        </div>
        
        <!-- Story Card Graphic -->
        <div style="position: relative; padding: 20px;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 350px; height: 350px; background: linear-gradient(135deg, rgba(0, 123, 255, 0.15) 0%, rgba(0, 242, 254, 0.15) 100%); filter: blur(70px); border-radius: 50%; z-index: 1;"></div>
            
            <div style="position: relative; z-index: 2; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 24px; padding: 40px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.04);">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                    <span style="font-size: 32px;">🎯</span>
                    <div>
                        <h4 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">ចក្ខុវិស័យរបស់យើង</h4>
                        <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 0; font-family: 'Kantumruy Pro', sans-serif !important;">Our Ultimate Vision</p>
                    </div>
                </div>
                <p style="font-size: 15px; color: #475569; line-height: 1.8; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important; font-style: italic;">
                    "ក្លាយជាសហគមន៍អប់រំ និងចែករំលែកចំណេះដឹងឌីជីថលដ៏ធំបំផុត និងមានទំនុកចិត្តខ្ពស់បំផុតនៅក្នុងប្រទេសកម្ពុជា ដើម្បីជួយសម្រេចរាល់ក្តីស្រមៃរបស់សិក្ខាកាមគ្រប់រូប។"
                </p>
                <div style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h5 style="font-size: 24px; font-weight: 900; color: #007bff; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">១០០%</h5>
                        <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0; font-family: 'Kantumruy Pro', sans-serif !important;">មាតិកាជំនាញជាក់ស្តែង</p>
                    </div>
                    <div>
                        <h5 style="font-size: 24px; font-weight: 900; color: #10b981; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">២៤/៧</h5>
                        <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0; font-family: 'Kantumruy Pro', sans-serif !important;">សិក្សាគ្រប់ពេល គ្រប់ទីកន្លែង</p>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</section>

<!-- Core Values Section -->
<section id="values" style="padding: 80px 0; background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <div style="text-align: center; margin-bottom: 50px;">
            <h2 style="font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 12px; font-family: 'Kantumruy Pro', sans-serif !important;">តម្លៃស្នូលរបស់យើង</h2>
            <p style="font-size: 18px; color: #64748b; font-family: 'Kantumruy Pro', sans-serif !important;">គុណតម្លៃទាំង ៤ ដែលធ្វើឱ្យ ReanDaily ក្លាយជាជម្រើសដ៏ល្អបំផុតសម្រាប់អ្នក</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px;">
            
            <!-- Value Card 1 -->
            <div class="value-card" style="background: #ffffff; padding: 35px 25px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.01); transition: all 0.3s ease;">
                <span style="font-size: 36px; display: inline-block; margin-bottom: 20px;">🎓</span>
                <h4 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; font-family: 'Kantumruy Pro', sans-serif !important;">គុណភាពខ្ពស់បំផុត</h4>
                <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">រាល់វគ្គសិក្សាទាំងអស់ត្រូវបានរៀបចំឡើងយ៉ាងសម្រិតសម្រាំង និងឆ្លងកាត់ការត្រួតពិនិត្យបច្ចេកទេសត្រឹមត្រូវ។</p>
            </div>
            
            <!-- Value Card 2 -->
            <div class="value-card" style="background: #ffffff; padding: 35px 25px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.01); transition: all 0.3s ease;">
                <span style="font-size: 36px; display: inline-block; margin-bottom: 20px;">⏳</span>
                <h4 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; font-family: 'Kantumruy Pro', sans-serif !important;">សិក្សាគ្មានដែនកំណត់</h4>
                <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">ចុះឈ្មោះសិក្សាម្តង អាចសិក្សាបានពេញមួយជីវិត។ សិក្សាឡើងវិញបានគ្រប់ពេលដែលត្រូវការ។</p>
            </div>
            
            <!-- Value Card 3 -->
            <div class="value-card" style="background: #ffffff; padding: 35px 25px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.01); transition: all 0.3s ease;">
                <span style="font-size: 36px; display: inline-block; margin-bottom: 20px;">👨‍🏫</span>
                <h4 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; font-family: 'Kantumruy Pro', sans-serif !important;">គ្រូឧទ្ទេសជំនាញពិត</h4>
                <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">ទទួលបានការចែករំលែកបទពិសោធន៍ផ្ទាល់ពីគ្រូឧទ្ទេសដែលកំពុងបម្រើការងារជាក់ស្តែងក្នុងវិស័យនីមួយៗ។</p>
            </div>
            
            <!-- Value Card 4 -->
            <div class="value-card" style="background: #ffffff; padding: 35px 25px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.01); transition: all 0.3s ease;">
                <span style="font-size: 36px; display: inline-block; margin-bottom: 20px;">🤝</span>
                <h4 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; font-family: 'Kantumruy Pro', sans-serif !important;">សហគមន៍គាំទ្រសិក្សា</h4>
                <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">សួរ-ឆ្លើយ និងពិភាក្សាជាមួយសិស្សរួមថ្នាក់ និងគ្រូឧទ្ទេសផ្ទាល់ ដើម្បីដោះស្រាយការលំបាកនានា។</p>
            </div>
            
        </div>
    </div>
</section>

<!-- Stats Showcase -->
<section style="padding: 60px 0; background: #ffffff;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-around; flex-wrap: wrap; gap: 30px;">
        <div style="text-align: center;">
            <h3 style="font-size: 44px; font-weight: 950; background: linear-gradient(135deg, #007bff 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 5px 0; letter-spacing: -0.03em; font-family: 'Kantumruy Pro', sans-serif !important;">១០K+</h3>
            <p style="font-size: 16px; color: #475569; font-weight: 600; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">សិស្សានុសិស្សសរុប</p>
        </div>
        <div style="text-align: center;">
            <h3 style="font-size: 44px; font-weight: 950; background: linear-gradient(135deg, #007bff 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 5px 0; letter-spacing: -0.03em; font-family: 'Kantumruy Pro', sans-serif !important;">២០០+</h3>
            <p style="font-size: 16px; color: #475569; font-weight: 600; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">វគ្គសិក្សាជំនាញ</p>
        </div>
        <div style="text-align: center;">
            <h3 style="font-size: 44px; font-weight: 950; background: linear-gradient(135deg, #007bff 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 5px 0; letter-spacing: -0.03em; font-family: 'Kantumruy Pro', sans-serif !important;">៥០+</h3>
            <p style="font-size: 16px; color: #475569; font-weight: 600; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">គ្រូឧទ្ទេសជំនាញ</p>
        </div>
        <div style="text-align: center;">
            <h3 style="font-size: 44px; font-weight: 950; background: linear-gradient(135deg, #007bff 0%, #00f2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 5px 0; letter-spacing: -0.03em; font-family: 'Kantumruy Pro', sans-serif !important;">៩៨%</h3>
            <p style="font-size: 16px; color: #475569; font-weight: 600; margin: 0; font-family: 'Kantumruy Pro', sans-serif !important;">ការពេញចិត្តខ្ពស់</p>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section style="background: #f8fafc; padding: 40px 0 100px 0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 60px 40px; border-radius: 24px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(0, 242, 254, 0.08) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; z-index: 1;"></div>
            
            <h2 style="font-size: 36px; font-weight: 900; color: #ffffff; margin: 0 0 15px 0; position: relative; z-index: 2; letter-spacing: -0.02em; font-family: 'Kantumruy Pro', sans-serif !important;">ចាប់ផ្តើមដំណើរការសិក្សាជាមួយ ReanDaily ថ្ងៃនេះ</h2>
            <p style="font-size: 18px; color: #94a3b8; max-width: 600px; margin: 0 auto 30px auto; line-height: 1.7; position: relative; z-index: 2; font-family: 'Kantumruy Pro', sans-serif !important;">
                អភិវឌ្ឍជំនាញផ្ទាល់ខ្លួន និងការងាររបស់អ្នកជាមួយវគ្គសិក្សាដែលមានការទទួលស្គាល់ និងមានតម្លៃសមរម្យបំផុត។
            </p>
            <a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>" class="btn btn-cta" style="background: linear-gradient(135deg, #007bff 0%, #00f2fe 100%) !important; color: #ffffff !important; font-weight: 700; font-size: 18px; padding: 16px 36px; border-radius: 8px; text-decoration: none; display: inline-block; position: relative; z-index: 2; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3); font-family: 'Kantumruy Pro', sans-serif !important;">ស្វែងរកវគ្គសិក្សាទាំងអស់</a>
        </div>
    </div>
</section>

<!-- Core CSS Hover Overrides -->
<style>
.value-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08) !important;
    border-color: #007bff !important;
}
@media (max-width: 991px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: 40px !important;
    }
}
</style>

</div>

<?php get_footer(); ?>
